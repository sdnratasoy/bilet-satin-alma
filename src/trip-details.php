<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$trip_id = intval($_GET['id'] ?? 0);
$origin = $_GET['origin'] ?? '';
$destination = $_GET['destination'] ?? '';
$date = $_GET['date'] ?? '';

$back_url = '/index.php';
if (!empty($origin) || !empty($destination) || !empty($date)) {
    $back_url .= '?search=1';
    if (!empty($origin)) $back_url .= '&origin=' . urlencode($origin);
    if (!empty($destination)) $back_url .= '&destination=' . urlencode($destination);
    if (!empty($date)) $back_url .= '&date=' . urlencode($date);
}

if ($trip_id <= 0) {
    setError("Geçersiz sefer.");
    header("Location: " . $back_url);
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
    header("Location: " . $back_url);
    exit;
}

$booked_seats = getBookedSeats($pdo, $trip_id);
$available_seats = $trip['capacity'] - count($booked_seats);

$page_title = "Sefer Detayları";
require_once __DIR__ . '/includes/header.php';
?>

<div class="trip-details-page">
    <div class="back-link">
        <a href="<?php echo $back_url; ?>" class="btn btn-secondary">← Seferlere Dön</a>
    </div>

    <div class="trip-details-container">
        <div class="trip-main-card">
            <div class="company-header">
                <div>
                    <h1><?php echo clean($trip['company_name']); ?></h1>
                    <p class="company-info">
                        🚌 Şehirlerarası Otobüs Seferi
                    </p>
                </div>
                <div class="trip-price-large">
                    <?php echo formatMoney($trip['price']); ?>
                </div>
            </div>

            <div class="route-display">
                <div class="route-point">
                    <div class="route-city">
                        <h2><?php echo clean($trip['origin_city']); ?></h2>
                        <p class="city-label">Kalkış Noktası</p>
                    </div>
                    <div class="route-time">
                        <span class="time"><?php echo date('H:i', strtotime($trip['departure_time'])); ?></span>
                        <span class="date"><?php echo date('d.m.Y', strtotime($trip['departure_time'])); ?></span>
                    </div>
                </div>

                <div class="route-arrow">
                    <div class="arrow-line"></div>
                    <div class="bus-icon">🚌</div>
                    <div class="arrow-line"></div>
                    <div class="duration-badge">
                        <?php
                        $duration = (strtotime($trip['arrival_time']) - strtotime($trip['departure_time'])) / 3600;
                        $hours = floor($duration);
                        $minutes = round(($duration - $hours) * 60);
                        echo $hours . 'sa ' . $minutes . 'dk';
                        ?>
                    </div>
                </div>

                <div class="route-point">
                    <div class="route-city">
                        <h2><?php echo clean($trip['destination_city']); ?></h2>
                        <p class="city-label">Varış Noktası</p>
                    </div>
                    <div class="route-time">
                        <span class="time"><?php echo date('H:i', strtotime($trip['arrival_time'])); ?></span>
                        <span class="date"><?php echo date('d.m.Y', strtotime($trip['arrival_time'])); ?></span>
                    </div>
                </div>
            </div>

            <div class="available-seats-info">
                <div class="seats-badge">
                    <span class="seats-icon">💺</span>
                    <span class="seats-text">Müsait Koltuk: <strong><?php echo $available_seats; ?></strong></span>
                </div>
            </div>
        </div>

        <div class="action-section">
            <?php if (isLoggedIn() && hasRole('user')): ?>
                <?php if ($available_seats > 0): ?>
                    <a href="/user/buy-ticket.php?trip_id=<?php echo $trip['id']; ?>"
                       class="btn btn-primary btn-large">
                        🎫 Bilet Satın Al
                    </a>
                <?php else: ?>
                    <button class="btn btn-disabled btn-large" disabled>
                        ❌ Bu Sefer Dolu
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <a href="/auth/login.php" class="btn btn-primary btn-large">
                    🔐 Bilet Almak İçin Giriş Yapın
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.trip-details-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.back-link {
    margin-bottom: 1.5rem;
}

.trip-details-container {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.trip-main-card {
    background: white;
    border-radius: 16px;
    padding: 2.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border: 1px solid var(--border-color);
}

.company-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--border-color);
}

.company-header h1 {
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.company-info {
    color: var(--text-light);
    font-size: 0.95rem;
}

.trip-price-large {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--accent-color);
}

.route-display {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
    margin: 2rem 0;
    padding: 2rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 12px;
}

.route-point {
    flex: 1;
    text-align: center;
}

.route-city h2 {
    color: var(--text-dark);
    margin-bottom: 0.5rem;
    font-size: 1.8rem;
}

.city-label {
    color: var(--text-light);
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.route-time {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.route-time .time {
    font-size: 2rem;
    font-weight: 700;
    color: var(--accent-color);
}

.route-time .date {
    color: var(--text-light);
    font-size: 0.9rem;
}

.route-arrow {
    flex: 0 0 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.arrow-line {
    width: 100%;
    height: 3px;
    background: var(--accent-color);
}

.bus-icon {
    font-size: 2.5rem;
}

.duration-badge {
    background: var(--accent-color);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.available-seats-info {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 2px solid var(--border-color);
    display: flex;
    justify-content: center;
}

.seats-badge {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    padding: 1rem 2rem;
    border-radius: 12px;
    border: 2px solid #10b981;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
}

.seats-icon {
    font-size: 1.8rem;
}

.seats-text {
    font-size: 1.1rem;
    color: var(--text-dark);
}

.seats-text strong {
    color: #10b981;
    font-size: 1.3rem;
    font-weight: 800;
}

.details-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

.details-card h3 {
    color: var(--text-dark);
    margin-bottom: 1.5rem;
    font-size: 1.3rem;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 1rem;
}

.details-grid {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: 8px;
    border-left: 4px solid var(--accent-color);
}

.detail-label {
    color: var(--text-light);
    font-weight: 600;
    font-size: 1rem;
}

.detail-value {
    font-weight: 600;
    color: var(--text-dark);
    font-size: 1.05rem;
    text-align: right;
}

.detail-value.price {
    color: var(--accent-color);
    font-size: 1.3rem;
    font-weight: 800;
}

.detail-value.available-text {
    color: #10b981;
    font-weight: 700;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-badge.available {
    background: #10b981;
    color: white;
}

.status-badge.full {
    background: #ef4444;
    color: white;
}

.action-section {
    display: flex;
    justify-content: center;
    padding: 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

.btn-large {
    padding: 1.25rem 3rem;
    font-size: 1.2rem;
    font-weight: 700;
}

@media (max-width: 968px) {
    .route-display {
        flex-direction: column;
        gap: 1.5rem;
    }

    .route-arrow {
        flex: 0 0 auto;
        width: 100%;
    }

    .arrow-line {
        height: 40px;
        width: 3px;
    }

    .details-card {
        padding: 1.5rem;
    }

    .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .detail-value {
        text-align: left;
    }

    .company-header {
        flex-direction: column;
        gap: 1rem;
    }

    .trip-price-large {
        font-size: 2rem;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
