<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');


$stats = [];


$stmt = $pdo->query("SELECT COUNT(*) as count FROM Bus_Company");
$stats['companies'] = $stmt->fetch()['count'];


$stmt = $pdo->query("SELECT COUNT(*) as count FROM Trips");
$stats['trips'] = $stmt->fetch()['count'];


$stmt = $pdo->query("SELECT COUNT(*) as count FROM Tickets WHERE status = 'active'");
$stats['tickets'] = $stmt->fetch()['count'];


$stmt = $pdo->query("SELECT COUNT(*) as count FROM User WHERE role = 'user'");
$stats['users'] = $stmt->fetch()['count'];


$stmt = $pdo->query("SELECT SUM(total_price) as total FROM Tickets WHERE status = 'active'");
$stats['revenue'] = $stmt->fetch()['total'] ?? 0;

$page_title = "Admin Dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel">
    <h1>Admin Dashboard</h1>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🚌</div>
            <div class="stat-info">
                <h3><?php echo $stats['companies']; ?></h3>
                <p>Otobüs Firmaları</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🚍</div>
            <div class="stat-info">
                <h3><?php echo $stats['trips']; ?></h3>
                <p>Toplam Sefer</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🎫</div>
            <div class="stat-info">
                <h3><?php echo $stats['tickets']; ?></h3>
                <p>Satılan Bilet</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-info">
                <h3><?php echo $stats['users']; ?></h3>
                <p>Kullanıcı</p>
            </div>
        </div>
        
        <div class="stat-card highlight">
            <div class="stat-icon">💰</div>
            <div class="stat-info">
                <h3><?php echo formatMoney($stats['revenue']); ?></h3>
                <p>Toplam Gelir</p>
            </div>
        </div>
    </div>
    
    <div class="admin-menu">
        <h2>Yönetim Paneli</h2>
        <div class="menu-grid">
            <a href="/admin/companies.php" class="menu-card">
                <div class="menu-icon">🚌</div>
                <h3>Firma Yönetimi</h3>
                <p>Otobüs firmalarını yönetin</p>
            </a>
            
            <a href="/admin/firma-admins.php" class="menu-card">
                <div class="menu-icon">👤</div>
                <h3>Firma Admin Yönetimi</h3>
                <p>Firma yetkililerini yönetin</p>
            </a>
            
            <a href="/admin/coupons.php" class="menu-card">
                <div class="menu-icon">🎟️</div>
                <h3>Kupon Yönetimi</h3>
                <p>Genel kuponları yönetin</p>
            </a>
            
            <a href="/admin/all-trips.php" class="menu-card">
                <div class="menu-icon">📋</div>
                <h3>Tüm Seferler</h3>
                <p>Tüm seferleri görüntüleyin</p>
            </a>
        </div>
    </div>
</div>

<style>
.admin-panel {
    max-width: 1200px;
    margin: 0 auto;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
}

.stat-card.highlight .stat-info p {
    color: rgba(255,255,255,0.9);
}

.admin-menu {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.menu-card {
    background: var(--bg-light);
    padding: 2rem;
    border-radius: 10px;
    text-decoration: none;
    color: var(--text-dark);
    transition: all 0.3s;
    border: 2px solid transparent;
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
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>