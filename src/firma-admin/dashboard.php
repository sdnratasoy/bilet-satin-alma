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
$active_ticket_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Tickets t
                       JOIN Trips tr ON t.trip_id = tr.id
                       WHERE tr.company_id = ?");
$stmt->execute([$company_id]);
$total_ticket_count = $stmt->fetch()['count'];

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
    <div class="panel-header">
        <div>
            <h1>Firma Admin Panel</h1>
            <h2 style="color: var(--text-light); font-size: 1.2rem; margin-top: 0.5rem;">
                <?php echo clean($company['name']); ?>
            </h2>
        </div>
    </div>

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
                <h3><?php echo $active_ticket_count; ?></h3>
                <p>Aktif Bilet</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-info">
                <h3><?php echo $total_ticket_count; ?></h3>
                <p>Toplam Bilet</p>
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

            <a href="/firma-admin/tickets.php" class="menu-card">
                <div class="menu-icon">🎫</div>
                <h3>Bilet Yönetimi</h3>
                <p>Biletleri görüntüleyin ve iptal edin</p>
            </a>

            <a href="/firma-admin/coupons.php" class="menu-card">
                <div class="menu-icon">🎟️</div>
                <h3>Kupon Yönetimi</h3>
                <p>İndirim kuponları</p>
            </a>

            <a href="/firma-admin/profile.php" class="menu-card">
                <div class="menu-icon">👤</div>
                <h3>Profilim</h3>
                <p>Profil bilgileri ve kişisel biletler</p>
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
.admin-panel {
    max-width: 1200px;
    margin: 0 auto;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s, box-shadow 0.3s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.stat-card.highlight {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
}

.stat-icon {
    font-size: 3rem;
}

.stat-info h3 {
    font-size: 2rem;
    margin: 0;
}

.stat-info p {
    margin: 0;
    color: var(--text-light);
    font-size: 0.9rem;
}

.stat-card.highlight .stat-info p {
    color: rgba(255,255,255,0.9);
}

.admin-menu {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.admin-menu h2 {
    margin-bottom: 1.5rem;
    color: var(--text-dark);
}

.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.menu-card {
    background: var(--bg-light);
    padding: 2rem;
    border-radius: 10px;
    text-decoration: none;
    color: var(--text-dark);
    transition: all 0.3s;
    border: 2px solid transparent;
    text-align: center;
}

.menu-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary-color);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.menu-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.menu-card h3 {
    margin-bottom: 0.5rem;
    color: var(--primary-color);
}

.menu-card p {
    margin: 0;
    color: var(--text-light);
    font-size: 0.9rem;
}

.recent-trips {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.recent-trips h2 {
    margin-bottom: 1.5rem;
    color: var(--text-dark);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
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
}

.data-table tr:hover {
    background: var(--bg-light);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>