<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('firma_admin');

$company_id = $_SESSION['company_id'];

$stmt = $pdo->prepare("SELECT * FROM Bus_Company WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Trips WHERE company_id = ?");
$stmt->execute([$company_id]);
$trip_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Tickets t 
                       JOIN Trips tr ON t.trip_id = tr.id 
                       WHERE tr.company_id = ? AND t.status = 'active'");
$stmt->execute([$company_id]);
$ticket_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT SUM(t.total_price) as total FROM Tickets t 
                       JOIN Trips tr ON t.trip_id = tr.id 
                       WHERE tr.company_id = ? AND t.status = 'active'");
$stmt->execute([$company_id]);
$revenue = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM Trips WHERE company_id = ? ORDER BY departure_time DESC LIMIT 5");
$stmt->execute([$company_id]);
$recent_trips = $stmt->fetchAll();

$page_title = "Firma Admin Dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel">
    <h1>Firma Admin Panel</h1>
    <h2><?php echo clean($company['name']); ?></h2>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🚍</div>
            <div class="stat-info">
                <h3><?php echo $trip_count; ?></h3>
                <p>Toplam Sefer</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🎫</div>
            <div class="stat-info">
                <h3><?php echo $ticket_count; ?></h3>
                <p>Satılan Bilet</p>
            </div>
        </div>
        
        <div class="stat-card highlight">
            <div class="stat-icon">💰</div>
            <div class="stat-info">
                <h3><?php echo formatMoney($revenue); ?></h3>
                <p>Toplam Gelir</p>
            </div>
        </div>
    </div>
    
    <div class="admin-menu">
        <h2>Yönetim</h2>
        <div class="menu-grid">
            <a href="/firma-admin/trips.php" class="menu-card">
                <div class="menu-icon">🚍</div>
                <h3>Sefer Yönetimi</h3>
                <p>Seferleri yönetin</p>
            </a>
            
            <a href="/firma-admin/coupons.php" class="menu-card">
                <div class="menu-icon">🎟️</div>
                <h3>Kupon Yönetimi</h3>
                <p>İndirim kuponları</p>
            </a>
        </div>
    </div>
    
    <?php if (!empty($recent_trips)): ?>
    <div class="recent-trips">
        <h2>Son Seferler</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Güzergah</th>
                    <th>Kalkış</th>
                    <th>Fiyat</th>
                    <th>Kapasite</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_trips as $trip): ?>
                    <?php
                    $booked = count(getBookedSeats($pdo, $trip['id']));
                    ?>
                    <tr>
                        <td><?php echo clean($trip['origin_city']) . ' → ' . clean($trip['destination_city']); ?></td>
                        <td><?php echo formatDate($trip['departure_time']); ?></td>
                        <td><?php echo formatMoney($trip['price']); ?></td>
                        <td><?php echo $booked . ' / ' . $trip['capacity']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.recent-trips {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-top: 2rem;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>