<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('firma_admin');

$company_id = $_SESSION['company_id'];

$stmt = $pdo->prepare("SELECT * FROM Bus_Company WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();

if (isset($_GET['cancel'])) {
    $ticket_id = intval($_GET['cancel']);

    $stmt = $pdo->prepare("SELECT t.*, tr.departure_time, tr.company_id, u.balance
                          FROM Tickets t
                          JOIN Trips tr ON t.trip_id = tr.id
                          JOIN User u ON t.user_id = u.id
                          WHERE t.id = ? AND tr.company_id = ?");
    $stmt->execute([$ticket_id, $company_id]);
    $ticket = $stmt->fetch();

    if ($ticket && $ticket['status'] === 'active') {
        $hours_until_departure = getHoursDifference($ticket['departure_time'], date('Y-m-d H:i:s'));

        if ($hours_until_departure < 1) {
            setError("Sefere 1 saatten az kaldığı için bilet iptal edilemez.");
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE Tickets SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$ticket_id]);

                $stmt = $pdo->prepare("DELETE FROM Booked_Seats WHERE ticket_id = ?");
                $stmt->execute([$ticket_id]);

                $stmt = $pdo->prepare("UPDATE User SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$ticket['total_price'], $ticket['user_id']]);

                $pdo->commit();
                setSuccess("Bilet başarıyla iptal edildi ve ücret kullanıcıya iade edildi.");
            } catch (Exception $e) {
                $pdo->rollBack();
                setError("Bilet iptal edilirken bir hata oluştu: " . $e->getMessage());
            }
        }
    } else {
        setError("Bilet bulunamadı veya iptal edilemez.");
    }

    header("Location: /firma-admin/tickets.php");
    exit;
}

$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_trip = isset($_GET['trip']) ? intval($_GET['trip']) : 0;

$query = "SELECT t.*, tr.origin_city, tr.destination_city, tr.departure_time, tr.arrival_time, tr.price as trip_price,
          u.full_name as passenger_name, u.email as passenger_email
          FROM Tickets t
          JOIN Trips tr ON t.trip_id = tr.id
          JOIN User u ON t.user_id = u.id
          WHERE tr.company_id = ?";

$params = [$company_id];

if ($filter_status !== 'all') {
    $query .= " AND t.status = ?";
    $params[] = $filter_status;
}

if ($filter_trip > 0) {
    $query .= " AND t.trip_id = ?";
    $params[] = $filter_trip;
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, origin_city, destination_city, departure_time
                      FROM Trips
                      WHERE company_id = ?
                      ORDER BY departure_time DESC
                      LIMIT 50");
$stmt->execute([$company_id]);
$trips = $stmt->fetchAll();

$page_title = "Bilet Yönetimi";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel">
    <div class="panel-header">
        <h1>Bilet Yönetimi</h1>
        <a href="/firma-admin/dashboard.php" class="btn btn-secondary">← Dashboard</a>
    </div>

    <div class="info-card">
        <h3>ℹ️ Bilgilendirme</h3>
        <p>Firma: <strong><?php echo clean($company['name']); ?></strong></p>
        <p class="warning-text">⚠️ Sefere 1 saatten az kalan biletler iptal edilemez.</p>
    </div>

    <div class="filter-card">
        <h2>Filtrele</h2>
        <form method="GET" action="" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label>Durum</label>
                    <select name="status">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Tümü</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>İptal Edilmiş</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Sefer</label>
                    <select name="trip">
                        <option value="0">Tüm Seferler</option>
                        <?php foreach ($trips as $trip): ?>
                            <option value="<?php echo $trip['id']; ?>"
                                    <?php echo $filter_trip == $trip['id'] ? 'selected' : ''; ?>>
                                <?php echo clean($trip['origin_city']); ?> → <?php echo clean($trip['destination_city']); ?>
                                (<?php echo formatDate($trip['departure_time']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Filtrele</button>
                <a href="/firma-admin/tickets.php" class="btn btn-secondary">Temizle</a>
            </div>
        </form>
    </div>

    <div class="data-table-card">
        <h2>Biletler (<?php echo count($tickets); ?>)</h2>
        <?php if (count($tickets) > 0): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bilet ID</th>
                            <th>Yolcu</th>
                            <th>Güzergah</th>
                            <th>Kalkış</th>
                            <th>Koltuk</th>
                            <th>Fiyat</th>
                            <th>Durum</th>
                            <th>Alım Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <?php
                            $is_past = strtotime($ticket['departure_time']) < time();
                            $hours_until_departure = getHoursDifference($ticket['departure_time'], date('Y-m-d H:i:s'));
                            $can_cancel = $ticket['status'] === 'active' && $hours_until_departure >= 1;
                            ?>
                            <tr class="<?php echo $ticket['status'] === 'cancelled' ? 'ticket-cancelled' : ''; ?>">
                                <td><strong>#<?php echo $ticket['id']; ?></strong></td>
                                <td>
                                    <div><?php echo clean($ticket['passenger_name']); ?></div>
                                    <small style="color: var(--text-light);"><?php echo clean($ticket['passenger_email']); ?></small>
                                </td>
                                <td>
                                    <div class="route">
                                        <span class="city"><?php echo clean($ticket['origin_city']); ?></span>
                                        <span class="arrow">→</span>
                                        <span class="city"><?php echo clean($ticket['destination_city']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo formatDate($ticket['departure_time']); ?></td>
                                <td><span class="seat-badge"><?php echo $ticket['seat_number']; ?></span></td>
                                <td><strong><?php echo formatMoney($ticket['total_price']); ?></strong></td>
                                <td>
                                    <?php if ($ticket['status'] === 'active'): ?>
                                        <?php if ($is_past): ?>
                                            <span class="status-badge completed">Tamamlandı</span>
                                        <?php else: ?>
                                            <span class="status-badge active">Aktif</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="status-badge cancelled">İptal Edildi</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($ticket['created_at']); ?></td>
                                <td>
                                    <?php if ($can_cancel): ?>
                                        <button class="btn-small btn-danger cancel-btn"
                                                data-id="<?php echo $ticket['id']; ?>"
                                                data-passenger="<?php echo clean($ticket['passenger_name']); ?>"
                                                data-route="<?php echo clean($ticket['origin_city']) . ' → ' . clean($ticket['destination_city']); ?>"
                                                data-hours="<?php echo round($hours_until_departure, 1); ?>">
                                            İptal Et
                                        </button>
                                    <?php elseif ($ticket['status'] === 'active' && $hours_until_departure < 1): ?>
                                        <span class="text-muted">İptal Edilemez</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-results">
                <p>Seçilen filtrelere uygun bilet bulunamadı.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="cancelModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-icon">⚠️</span>
            <h2>Bileti İptal Et</h2>
        </div>
        <div class="modal-body">
            <p>Bu bileti iptal etmek istediğinizden emin misiniz?</p>
            <div class="ticket-info">
                <p><strong>Yolcu:</strong> <span class="passenger-display"></span></p>
                <p><strong>Güzergah:</strong> <span class="route-display"></span></p>
                <p><strong>Kalkışa Kalan Süre:</strong> <span class="hours-display"></span> saat</p>
            </div>
            <p class="warning-text">💰 Bilet ücreti yolcuya iade edilecektir.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary cancel-modal-btn">İptal</button>
            <button class="btn btn-danger confirm-cancel-btn">Evet, İptal Et</button>
        </div>
    </div>
</div>

<style>
.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.info-card {
    background: #e0f2fe;
    border: 1px solid #0ea5e9;
    border-left: 4px solid #0ea5e9;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.info-card h3 {
    margin: 0 0 1rem 0;
    color: #0369a1;
}

.info-card p {
    margin: 0.5rem 0;
    color: var(--text-dark);
}

.info-card .warning-text {
    color: #ef4444;
    font-weight: 500;
    margin-top: 1rem;
    padding: 0.75rem;
    background: #fee2e2;
    border-radius: 5px;
}

.filter-card {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.filter-form {
    margin-top: 1rem;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: bold;
    color: var(--text-dark);
    font-size: 0.875rem;
}

.form-group input,
.form-group select {
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    font-size: 1rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
}

.data-table-card {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.data-table th,
.data-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background: var(--bg-light);
    font-weight: bold;
    color: var(--text-dark);
    white-space: nowrap;
}

.data-table td:last-child {
    white-space: nowrap;
}

.data-table tr:hover {
    background: var(--bg-light);
}

.data-table tr.ticket-cancelled {
    opacity: 0.6;
}

.route {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.route .city {
    font-weight: bold;
}

.route .arrow {
    color: var(--primary-color);
}

.seat-badge {
    background: var(--primary-color);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: bold;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: bold;
    display: inline-block;
    white-space: nowrap;
}

.status-badge.active {
    background: #10b981;
    color: white;
}

.status-badge.cancelled {
    background: #ef4444;
    color: white;
}

.status-badge.completed {
    background: #6b7280;
    color: white;
}

.text-muted {
    color: var(--text-light);
    font-size: 0.875rem;
}

.no-results {
    text-align: center;
    padding: 3rem;
    color: var(--text-light);
}

.btn-small {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    text-decoration: none;
    display: inline-block;
    border-radius: 5px;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1rem;
}

.btn-primary:hover {
    background: var(--secondary-color);
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-secondary {
    background: var(--bg-light);
    color: var(--text-dark);
    padding: 0.75rem 1.5rem;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    text-decoration: none;
    display: inline-block;
    font-size: 1rem;
}

.btn-secondary:hover {
    background: var(--border-color);
}

.cancel-btn {
    background: #ef4444;
    color: white;
}

.cancel-btn:hover {
    background: #dc2626;
}

.modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);backdrop-filter:blur(4px);animation:fadeIn 0.3s ease}.modal.show{display:flex;justify-content:center;align-items:center}@keyframes fadeIn{from{opacity:0}to{opacity:1}}@keyframes slideIn{from{transform:translateY(-50px);opacity:0}to{transform:translateY(0);opacity:1}}.modal-content{background:white;border-radius:12px;max-width:500px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:slideIn 0.3s ease}.modal-header{padding:2rem 2rem 1rem 2rem;text-align:center}.modal-icon{font-size:4rem;display:block;margin-bottom:1rem}.modal-header h2{margin:0;color:var(--text-dark);font-size:1.5rem}.modal-body{padding:1rem 2rem 2rem 2rem;text-align:center}.modal-body p{margin:0.5rem 0;color:var(--text-dark)}.ticket-info{background:var(--bg-light);padding:1rem;border-radius:8px;margin:1rem 0;text-align:left}.ticket-info p{margin:0.5rem 0}.warning-text{color:#10b981;font-weight:500;margin-top:1.5rem!important;padding:1rem;background:#d1fae5;border-radius:8px;border-left:4px solid #10b981}.modal-footer{padding:1rem 2rem 2rem 2rem;display:flex;gap:1rem;justify-content:center}.modal-footer .btn{padding:0.75rem 2rem;border:none;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;transition:all 0.2s}.modal-footer .btn-secondary{background:var(--bg-light);color:var(--text-dark);border:1px solid var(--border-color)}.modal-footer .btn-secondary:hover{background:var(--border-color)}.modal-footer .btn-danger{background:#ef4444;color:white}.modal-footer .btn-danger:hover{background:#dc2626}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('cancelModal');
    const cancelButtons = document.querySelectorAll('.cancel-btn');
    const cancelModalBtn = modal.querySelector('.cancel-modal-btn');
    const confirmBtn = modal.querySelector('.confirm-cancel-btn');
    const passengerDisplay = modal.querySelector('.passenger-display');
    const routeDisplay = modal.querySelector('.route-display');
    const hoursDisplay = modal.querySelector('.hours-display');

    let cancelId = null;

    cancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            cancelId = this.getAttribute('data-id');
            const passenger = this.getAttribute('data-passenger');
            const route = this.getAttribute('data-route');
            const hours = this.getAttribute('data-hours');

            passengerDisplay.textContent = passenger;
            routeDisplay.textContent = route;
            hoursDisplay.textContent = hours;
            modal.classList.add('show');
        });
    });

    cancelModalBtn.addEventListener('click', function() {
        modal.classList.remove('show');
        cancelId = null;
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('show');
            cancelId = null;
        }
    });

    confirmBtn.addEventListener('click', function() {
        if (cancelId) {
            window.location.href = '?cancel=' + cancelId;
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) {
            modal.classList.remove('show');
            cancelId = null;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
