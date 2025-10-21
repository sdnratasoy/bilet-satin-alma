<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('firma_admin');

$user = getCurrentUser($pdo);
if (!$user) {
    setError("Kullanıcı bilgisi alınamadı. Lütfen tekrar giriş yapın.");
    header("Location: /auth/login.php");
    exit;
}

// Firma bilgisini çek
$stmt = $pdo->prepare("SELECT * FROM Bus_Company WHERE id = ?");
$stmt->execute([$user['company_id']]);
$company = $stmt->fetch();

$page_title = "Profilim";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel">
    <div class="panel-header">
        <h1>👤 Profilim</h1>
        <a href="/firma-admin/dashboard.php" class="btn btn-secondary">← Dashboard</a>
    </div>

    <div class="profile-section">
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-icon">👤</div>
                <h2><?php echo clean($user['full_name']); ?></h2>
                <p class="role-badge">Firma Yöneticisi</p>
            </div>

            <div class="profile-info">
                <div class="info-row">
                    <span class="info-label">📧 E-posta:</span>
                    <span class="info-value"><?php echo clean($user['email']); ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">🏢 Firma:</span>
                    <span class="info-value"><?php echo clean($company['name'] ?? 'Atanmamış'); ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">💳 Bakiye:</span>
                    <span class="info-value balance"><?php echo formatMoney($user['balance']); ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">📅 Kayıt Tarihi:</span>
                    <span class="info-value"><?php echo formatDate($user['created_at']); ?></span>
                </div>
            </div>
        </div>

        <div class="info-card">
            <h3>ℹ️ Bilgilendirme</h3>
            <p>Bu sayfada profil bilgilerinizi görüntüleyebilirsiniz.</p>
            <p>Firma ile ilgili biletleri görüntülemek için <a href="/firma-admin/tickets.php"><strong>Bilet Yönetimi</strong></a> sayfasını kullanın.</p>
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

.profile-section {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 3rem;
}

.profile-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.profile-header {
    text-align: center;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 1.5rem;
}

.profile-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.profile-header h2 {
    margin: 0 0 0.5rem 0;
    color: var(--text-dark);
}

.role-badge {
    display: inline-block;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    padding: 0.5rem 1.5rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: bold;
}

.profile-info {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--bg-light);
    border-radius: 8px;
}

.info-label {
    font-weight: 600;
    color: var(--text-dark);
}

.info-value {
    color: var(--text-light);
}

.info-value.balance {
    font-size: 1.2rem;
    font-weight: bold;
    color: var(--primary-color);
}

.info-card {
    background: #e0f2fe;
    border: 1px solid #0ea5e9;
    border-left: 4px solid #0ea5e9;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.info-card h3 {
    margin: 0 0 1rem 0;
    color: #0369a1;
}

.info-card p {
    margin: 0.5rem 0;
    color: var(--text-dark);
}

.info-card a {
    color: var(--primary-color);
    text-decoration: none;
}

.info-card a:hover {
    text-decoration: underline;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: var(--secondary-color);
}

.btn-secondary {
    background: var(--bg-light);
    color: var(--text-dark);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--border-color);
}

@media (max-width: 768px) {
    .profile-section {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
