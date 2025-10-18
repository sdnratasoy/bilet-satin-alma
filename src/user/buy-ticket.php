<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('user');

$user = getCurrentUser($pdo);

if (!$user) {
    setError("Kullanıcı bulunamadı.");
    header("Location: /auth/login.php");
    exit;
}

$trip_id = intval($_GET['trip_id'] ?? 0);

if ($trip_id <= 0) {
    setError("Geçersiz sefer.");
    header("Location: /index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT t.*, bc.name as company_name 
                       FROM Trips t 
                       JOIN Bus_Company bc ON t.company_id = bc.id 
                       WHERE t.id = ?");
$stmt->execute([$trip_id]);
$trip = $stmt->fetch();

if (!$trip) {
    setError("Sefer bulunamadı.");
    header("Location: /index.php");
    exit;
}

$booked_seats = getBookedSeats($pdo, $trip_id);
$available_seats = $trip['capacity'] - count($booked_seats);

if ($available_seats <= 0) {
    setError("Bu sefer için müsait koltuk kalmamıştır.");
    header("Location: /index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seat_number = intval($_POST['seat_number'] ?? 0);
    $gender = trim($_POST['gender'] ?? '');
    $coupon_code = trim($_POST['coupon_code'] ?? '');
    
    if ($seat_number <= 0) {
        setError("Lütfen bir koltuk seçin.");
    } elseif (empty($gender)) {
        setError("Lütfen cinsiyet seçin.");
    } elseif (isSeatBooked($pdo, $trip_id, $seat_number)) {
        setError("Bu koltuk zaten dolu.");
    } else {
        $final_price = floatval($trip['price']);
        $discount = 0;
        
        if (!empty($coupon_code)) {
            $coupon_result = validateCoupon($pdo, $coupon_code, $trip['company_id']);
            if ($coupon_result['valid']) {
                $discount = floatval($coupon_result['discount']);
                $final_price = $trip['price'] * (1 - $discount / 100);
                
                $stmt = $pdo->prepare("UPDATE Coupons SET used_count = used_count + 1 WHERE id = ?");
                $stmt->execute([$coupon_result['coupon_id']]);
            }
        }
        
        if (floatval($user['balance']) < $final_price) {
            setError("Yetersiz bakiye. Bakiyeniz: " . formatMoney($user['balance']));
        } else {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("INSERT INTO Tickets (trip_id, user_id, seat_number, total_price) 
                                      VALUES (?, ?, ?, ?)");
                $stmt->execute([$trip_id, $user['id'], $seat_number, $final_price]);
                $ticket_id = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("INSERT INTO Booked_Seats (trip_id, seat_number, ticket_id) 
                                      VALUES (?, ?, ?)");
                $stmt->execute([$trip_id, $seat_number, $ticket_id]);
                
                $stmt = $pdo->prepare("UPDATE User SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$final_price, $user['id']]);
                
                $pdo->commit();
                
                setSuccess("Biletiniz başarıyla satın alındı! Bilet No: #" . $ticket_id);
                header("Location: /user/tickets.php");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                setError("Bilet alımında bir hata oluştu.");
            }
        }
    }
}

$page_title = "Bilet Al";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ticket-purchase">
    <div class="trip-info-card">
        <div class="company-header">
            <h2><?php echo clean($trip['company_name']); ?></h2>
            <div class="trip-time">
                <span class="time-badge">⏱️ <?php echo date('H:i', strtotime($trip['departure_time'])); ?></span>
                <span class="duration">(<?php 
                    $duration = (strtotime($trip['arrival_time']) - strtotime($trip['departure_time'])) / 3600;
                    echo floor($duration) . ' Saat ' . (($duration - floor($duration)) * 60) . ' Dakika';
                ?>)</span>
            </div>
        </div>
        
        <div class="route-info">
            <div class="route-point">
                <strong><?php echo clean($trip['origin_city']); ?></strong>
                <small><?php echo date('d.m.Y', strtotime($trip['departure_time'])); ?></small>
            </div>
            <div class="route-arrow">
                <div class="arrow-line"></div>
                <span>🚌</span>
            </div>
            <div class="route-point">
                <strong><?php echo clean($trip['destination_city']); ?></strong>
                <small><?php echo date('d.m.Y', strtotime($trip['arrival_time'])); ?></small>
            </div>
        </div>
        
        <div class="trip-details">
            <div class="detail-item">
                <span>💺 Koltuk Sayısı</span>
                <strong><?php echo $trip['capacity']; ?></strong>
            </div>
            <div class="detail-item">
                <span>✅ Müsait</span>
                <strong class="available"><?php echo $available_seats; ?></strong>
            </div>
            <div class="detail-item">
                <span>💰 Bilet Fiyatı</span>
                <strong class="price"><?php echo formatMoney($trip['price']); ?></strong>
            </div>
        </div>
    </div>

    <form method="POST" action="" id="ticket-form">
        <input type="hidden" name="seat_number" id="selected_seat" value="">
        <input type="hidden" name="gender" id="selected_gender" value="">
        
        <div class="seat-selection-card">
            <h3>🚌 Koltuk Seçimi</h3>
            
            <div class="seat-legend">
                <div class="legend-item">
                    <div class="legend-icon male"></div>
                    <span>Dolu Koltuk - Erkek</span>
                </div>
                <div class="legend-item">
                    <div class="legend-icon female"></div>
                    <span>Dolu Koltuk - Kadın</span>
                </div>
                <div class="legend-item">
                    <div class="legend-icon empty"></div>
                    <span>Boş Koltuk</span>
                </div>
                <div class="legend-item">
                    <div class="legend-icon selected"></div>
                    <span>Seçilen Koltuk</span>
                </div>
            </div>

            <div class="bus-layout">
                <div class="seats-container">
                    <?php
                    $capacity = 40;
                    $seats_per_row = 4;
                    $total_rows = 10;

                    for ($row = 1; $row <= $total_rows; $row++):
                    ?>
                        <div class="seat-column">
                            <div class="column-number"><?php echo $row; ?></div>

                            <div class="column-seats">
                                <?php
                                $seat1 = ($row - 1) * 4 + 1;
                                $seat2 = ($row - 1) * 4 + 2;
                                $seat3 = ($row - 1) * 4 + 3;
                                $seat4 = ($row - 1) * 4 + 4;

                                $is_booked1 = in_array($seat1, $booked_seats);
                                $is_booked2 = in_array($seat2, $booked_seats);
                                $is_booked3 = in_array($seat3, $booked_seats);
                                $is_booked4 = in_array($seat4, $booked_seats);
                                ?>

                                <div class="seat-pair">
                                    <div class="seat <?php echo $is_booked1 ? 'booked male' : 'available'; ?>"
                                         data-seat="<?php echo $seat1; ?>"
                                         onclick="openGenderModal(<?php echo $seat1; ?>, <?php echo $is_booked1 ? 'true' : 'false'; ?>)">
                                        <?php echo $seat1; ?>
                                    </div>
                                    <div class="seat <?php echo $is_booked2 ? 'booked female' : 'available'; ?>"
                                         data-seat="<?php echo $seat2; ?>"
                                         onclick="openGenderModal(<?php echo $seat2; ?>, <?php echo $is_booked2 ? 'true' : 'false'; ?>)">
                                        <?php echo $seat2; ?>
                                    </div>
                                </div>

                                <div class="aisle-horizontal"></div>

                                <div class="seat-pair">
                                    <div class="seat <?php echo $is_booked3 ? 'booked male' : 'available'; ?>"
                                         data-seat="<?php echo $seat3; ?>"
                                         onclick="openGenderModal(<?php echo $seat3; ?>, <?php echo $is_booked3 ? 'true' : 'false'; ?>)">
                                        <?php echo $seat3; ?>
                                    </div>
                                    <div class="seat <?php echo $is_booked4 ? 'booked female' : 'available'; ?>"
                                         data-seat="<?php echo $seat4; ?>"
                                         onclick="openGenderModal(<?php echo $seat4; ?>, <?php echo $is_booked4 ? 'true' : 'false'; ?>)">
                                        <?php echo $seat4; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="purchase-section">
            <div class="coupon-input">
                <label>🎟️ İndirim Kuponu (Opsiyonel)</label>
                <input type="text" name="coupon_code" placeholder="Kupon kodunuz varsa girin">
            </div>
            
            <div class="summary-card">
                <h4>Bilet Özeti</h4>
                <div class="summary-item">
                    <span>Seçilen Koltuk:</span>
                    <strong id="display-seat">Henüz seçilmedi</strong>
                </div>
                <div class="summary-item">
                    <span>Cinsiyet:</span>
                    <strong id="display-gender">-</strong>
                </div>
                <div class="summary-item">
                    <span>Bilet Fiyatı:</span>
                    <strong><?php echo formatMoney($trip['price']); ?></strong>
                </div>
                <div class="summary-item total">
                    <span>Bakiyeniz:</span>
                    <strong><?php echo formatMoney($user['balance'] ?? 0); ?></strong>
                </div>
            </div>
            
            <button type="submit" class="btn-purchase" id="submit-btn" disabled>
                ✅ ONAYLA VE DEVAM ET
            </button>
        </div>
    </form>
</div>

<div id="genderModal" class="modal">
    <div class="modal-content">
        <h3>Cinsiyet Seçimi</h3>
        <p>Koltuk <strong id="modal-seat-number"></strong> için cinsiyetinizi seçin:</p>
        <div class="gender-buttons">
            <button type="button" class="gender-btn male" onclick="selectGender('Erkek')">
                <span class="gender-icon">👨</span>
                <span>Erkek</span>
            </button>
            <button type="button" class="gender-btn female" onclick="selectGender('Kadın')">
                <span class="gender-icon">👩</span>
                <span>Kadın</span>
            </button>
        </div>
        <button type="button" class="btn-cancel" onclick="closeGenderModal()">İptal</button>
    </div>
</div>

<style>
.ticket-purchase {
    max-width: 1000px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.trip-info-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid var(--border-color);
}

.company-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.time-badge {
    background: var(--accent-color);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
}

.duration {
    color: var(--text-light);
    font-size: 0.9rem;
    margin-left: 0.5rem;
}

.route-info {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 2rem 0;
}

.route-point {
    flex: 1;
    text-align: center;
}

.route-point strong {
    display: block;
    font-size: 1.3rem;
    color: var(--text-dark);
    margin-bottom: 0.25rem;
}

.route-arrow {
    flex: 0 0 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.arrow-line {
    height: 2px;
    background: var(--accent-color);
    flex: 1;
}

.route-arrow span {
    font-size: 1.5rem;
    margin: 0 0.5rem;
}

.trip-details {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

.detail-item {
    text-align: center;
}

.detail-item span {
    display: block;
    color: var(--text-light);
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.detail-item strong {
    display: block;
    font-size: 1.2rem;
    color: var(--text-dark);
}

.detail-item strong.available {
    color: var(--success-color);
}

.detail-item strong.price {
    color: var(--accent-color);
}

.seat-selection-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid var(--border-color);
}

.seat-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin: 1.5rem 0;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: 8px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.legend-icon {
    width: 30px;
    height: 30px;
    border-radius: 6px;
    border: 2px solid #cbd5e1;
}

.legend-icon.male {
    background: linear-gradient(135deg, #93c5fd 0%, #60a5fa 100%);
}

.legend-icon.female {
    background: linear-gradient(135deg, #fda4af 0%, #fb7185 100%);
}

.legend-icon.empty {
    background: white;
    border-color: #cbd5e1;
}

.legend-icon.selected {
    background: var(--accent-color);
    border-color: var(--secondary-color);
}

.bus-layout {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: 2rem;
    border-radius: 20px;
    border: 3px solid #cbd5e1;
    position: relative;
    overflow-x: auto;
}

.seats-container {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 12px;
    overflow-x: auto;
}

.seat-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.column-number {
    background: var(--accent-color);
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.85rem;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
}

.column-seats {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.seat-pair {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.aisle-horizontal {
    height: 15px;
    border-top: 2px dashed #cbd5e1;
    margin: 0.25rem 0;
}

.seat {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px 8px 12px 12px;
    font-weight: bold;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid #cbd5e1;
    background: white;
}

.seat.available:hover {
    transform: scale(1.05);
    border-color: var(--accent-color);
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.seat.booked {
    cursor: not-allowed;
    opacity: 0.7;
}

.seat.booked.male {
    background: linear-gradient(135deg, #93c5fd 0%, #60a5fa 100%);
    color: white;
    border-color: #3b82f6;
}

.seat.booked.female {
    background: linear-gradient(135deg, #fda4af 0%, #fb7185 100%);
    color: white;
    border-color: #f43f5e;
}

.seat.selected {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    animation: pulse 1s ease-in-out;
}

.seat.selected.male {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
    color: white !important;
    border-color: #1e40af !important;
}

.seat.selected.female {
    background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%) !important;
    color: white !important;
    border-color: #be123c !important;
}

@keyframes pulse {
    0%, 100% { transform: scale(1.1); }
    50% { transform: scale(1.15); }
}

.purchase-section {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid var(--border-color);
}

.coupon-input {
    margin-bottom: 1.5rem;
}

.coupon-input label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-dark);
}

.coupon-input input {
    width: 100%;
    padding: 0.875rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 1rem;
}

.summary-card {
    background: var(--bg-light);
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.summary-card h4 {
    margin-bottom: 1rem;
    color: var(--text-dark);
}

.summary-item {
    display: flex;
    justify-content: space-between;
    margin: 0.75rem 0;
    font-size: 1rem;
}

.summary-item.total {
    padding-top: 0.75rem;
    margin-top: 0.75rem;
    border-top: 2px solid var(--border-color);
    font-size: 1.1rem;
}

.summary-item strong {
    color: var(--accent-color);
}

.btn-purchase {
    width: 100%;
    padding: 1rem;
    background: var(--accent-color);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-purchase:hover:not(:disabled) {
    background: var(--secondary-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.btn-purchase:disabled {
    background: #cbd5e1;
    cursor: not-allowed;
}


.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
}

.modal-content {
    background: white;
    margin: 10% auto;
    padding: 2rem;
    border-radius: 16px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
}

.modal-content h3 {
    margin-bottom: 1rem;
    color: var(--text-dark);
}

.modal-content p {
    margin-bottom: 1.5rem;
    color: var(--text-light);
}

.gender-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.gender-btn {
    padding: 1.5rem;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.gender-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.gender-btn.male {
    border-color: #3b82f6;
}

.gender-btn.male:hover {
    background: #eff6ff;
    border-width: 3px;
}

.gender-btn.female {
    border-color: #f43f5e;
}

.gender-btn.female:hover {
    background: #fff1f2;
    border-width: 3px;
}

.gender-icon {
    font-size: 3rem;
}

.gender-btn span:last-child {
    font-weight: 600;
    font-size: 1.1rem;
}

.btn-cancel {
    width: 100%;
    padding: 0.75rem;
    background: #e5e7eb;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.btn-cancel:hover {
    background: #d1d5db;
}

@media (max-width: 768px) {
    .bus-layout {
        padding: 1rem;
    }

    .seats-container {
        padding: 0.5rem;
    }

    .seat-column {
        gap: 0.35rem;
    }

    .column-number {
        width: 25px;
        height: 25px;
        font-size: 0.75rem;
    }

    .seat {
        width: 38px;
        height: 38px;
        font-size: 0.75rem;
    }
    
    .trip-details {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
let selectedSeat = null;
let selectedGender = null;
let currentModalSeat = null;

function openGenderModal(seatNumber, isBooked) {
    if (isBooked) {
        alert('Bu koltuk dolu!');
        return;
    }
    
    currentModalSeat = seatNumber;
    document.getElementById('modal-seat-number').textContent = seatNumber;
    document.getElementById('genderModal').style.display = 'block';
}

function closeGenderModal() {
    document.getElementById('genderModal').style.display = 'none';
    currentModalSeat = null;
}

function selectGender(gender) {
    if (currentModalSeat === null) return;
    
    document.querySelectorAll('.seat.selected').forEach(seat => {
        if (!seat.classList.contains('booked')) {
            seat.classList.remove('selected', 'male', 'female');
        }
    });
    
    const seatElement = document.querySelector(`[data-seat="${currentModalSeat}"]`);
    if (seatElement) {
        seatElement.classList.add('selected');
        
        if (gender === 'Erkek') {
            seatElement.classList.add('male');
        } else if (gender === 'Kadın') {
            seatElement.classList.add('female');
        }
        
        console.log('Koltuk classes:', seatElement.className); 
    }
    
    selectedSeat = currentModalSeat;
    selectedGender = gender;
    
    document.getElementById('selected_seat').value = selectedSeat;
    document.getElementById('selected_gender').value = selectedGender;
    document.getElementById('display-seat').textContent = 'Koltuk ' + selectedSeat;
    document.getElementById('display-gender').textContent = selectedGender + (gender === 'Erkek' ? ' 👨' : ' 👩');
    document.getElementById('submit-btn').disabled = false;
    
    closeGenderModal();
}

window.onclick = function(event) {
    const modal = document.getElementById('genderModal');
    if (event.target == modal) {
        closeGenderModal();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>