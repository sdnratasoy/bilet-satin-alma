<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('firma_admin');

$company_id = $_SESSION['company_id'];

$stmt = $pdo->prepare("SELECT * FROM Bus_Company WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code = strtoupper(trim($_POST['code']));
    $discount = floatval($_POST['discount']);
    $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
    $expire_date = $_POST['expire_date'];

    if (empty($code) || empty($discount) || empty($expire_date)) {
        setError("Kod, indirim oranı ve son kullanma tarihi zorunludur.");
    } elseif ($discount <= 0 || $discount > 100) {
        setError("İndirim oranı 0 ile 100 arasında olmalıdır.");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Coupons WHERE code = ? AND company_id = ?");
        $stmt->execute([$code, $company_id]);
        if ($stmt->fetch()['count'] > 0) {
            setError("Bu kupon kodu firmanız için zaten kullanılıyor.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO Coupons (code, discount, company_id, usage_limit, expire_date) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$code, $discount, $company_id, $usage_limit, $expire_date])) {
                setSuccess("Kupon başarıyla eklendi.");
            } else {
                setError("Kupon eklenirken bir hata oluştu.");
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_coupon'])) {
    $id = intval($_POST['id']);
    $code = strtoupper(trim($_POST['code']));
    $discount = floatval($_POST['discount']);
    $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
    $expire_date = $_POST['expire_date'];

    if (empty($code) || empty($discount) || empty($expire_date)) {
        setError("Kod, indirim oranı ve son kullanma tarihi zorunludur.");
    } elseif ($discount <= 0 || $discount > 100) {
        setError("İndirim oranı 0 ile 100 arasında olmalıdır.");
    } else {
        $stmt = $pdo->prepare("SELECT company_id FROM Coupons WHERE id = ?");
        $stmt->execute([$id]);
        $coupon = $stmt->fetch();

        if ($coupon && $coupon['company_id'] == $company_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Coupons WHERE code = ? AND company_id = ? AND id != ?");
            $stmt->execute([$code, $company_id, $id]);
            if ($stmt->fetch()['count'] > 0) {
                setError("Bu kupon kodu firmanız için başka bir kupon tarafından kullanılıyor.");
            } else {
                $stmt = $pdo->prepare("UPDATE Coupons SET code = ?, discount = ?, usage_limit = ?, expire_date = ? WHERE id = ?");
                if ($stmt->execute([$code, $discount, $usage_limit, $expire_date, $id])) {
                    setSuccess("Kupon başarıyla güncellendi.");
                } else {
                    setError("Kupon güncellenirken bir hata oluştu.");
                }
            }
        } else {
            setError("Bu kuponu düzenleme yetkiniz yok.");
        }
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $stmt = $pdo->prepare("SELECT company_id FROM Coupons WHERE id = ?");
    $stmt->execute([$id]);
    $coupon = $stmt->fetch();

    if ($coupon && $coupon['company_id'] == $company_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM Coupons WHERE id = ?");
            $stmt->execute([$id]);
            setSuccess("Kupon başarıyla silindi.");
        } catch (Exception $e) {
            setError("Kupon silinirken bir hata oluştu: " . $e->getMessage());
        }
    } else {
        setError("Bu kuponu silme yetkiniz yok.");
    }

    header("Location: /firma-admin/coupons.php");
    exit;
}

$editing_coupon = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM Coupons WHERE id = ? AND company_id = ?");
    $stmt->execute([$id, $company_id]);
    $editing_coupon = $stmt->fetch();

    if (!$editing_coupon) {
        setError("Bu kuponu düzenleme yetkiniz yok veya kupon bulunamadı.");
        header("Location: /firma-admin/coupons.php");
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM Coupons WHERE company_id = ? ORDER BY created_at DESC");
$stmt->execute([$company_id]);
$coupons = $stmt->fetchAll();

$page_title = "Kupon Yönetimi";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel">
    <div class="panel-header">
        <h1>Firma Kupon Yönetimi</h1>
        <a href="/firma-admin/dashboard.php" class="btn btn-secondary">← Dashboard</a>
    </div>

    <div class="add-form-card">
        <h2><?php echo $editing_coupon ? 'Kupon Düzenle' : 'Yeni Firma Kuponu Ekle'; ?></h2>
        <p class="info-text">Bu kuponlar sadece <strong><?php echo clean($company['name']); ?></strong> firması için geçerlidir.</p>
        <form method="POST" action="" class="admin-form">
            <?php if ($editing_coupon): ?>
                <input type="hidden" name="id" value="<?php echo $editing_coupon['id']; ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label>Kupon Kodu</label>
                    <input type="text" name="code" placeholder="KUPONKODU"
                           value="<?php echo $editing_coupon ? clean($editing_coupon['code']) : ''; ?>"
                           style="text-transform: uppercase;" required>
                    <small>Otomatik olarak büyük harfe çevrilir</small>
                </div>

                <div class="form-group">
                    <label>İndirim Oranı (%)</label>
                    <input type="number" name="discount" placeholder="10" min="0.01" max="100" step="0.01"
                           value="<?php echo $editing_coupon ? $editing_coupon['discount'] : ''; ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Kullanım Limiti (Boş bırakırsanız sınırsız)</label>
                    <input type="number" name="usage_limit" placeholder="100" min="1"
                           value="<?php echo $editing_coupon && $editing_coupon['usage_limit'] ? $editing_coupon['usage_limit'] : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Son Kullanma Tarihi</label>
                    <input type="datetime-local" name="expire_date"
                           value="<?php echo $editing_coupon ? date('Y-m-d\TH:i', strtotime($editing_coupon['expire_date'])) : ''; ?>" required>
                </div>
            </div>

            <div class="form-actions">
                <?php if ($editing_coupon): ?>
                    <button type="submit" name="edit_coupon" class="btn btn-primary">Güncelle</button>
                    <a href="/firma-admin/coupons.php" class="btn btn-secondary">İptal</a>
                <?php else: ?>
                    <button type="submit" name="add_coupon" class="btn btn-primary">Ekle</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="data-table-card">
        <h2>Firma Kuponları (<?php echo count($coupons); ?>)</h2>
        <?php if (count($coupons) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kod</th>
                        <th>İndirim</th>
                        <th>Kullanım</th>
                        <th>Son Kullanma</th>
                        <th>Durum</th>
                        <th>Oluşturulma</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $coupon): ?>
                        <?php
                        $is_expired = strtotime($coupon['expire_date']) < time();
                        $is_limit_reached = $coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit'];
                        $status = $is_expired ? 'expired' : ($is_limit_reached ? 'limit-reached' : 'active');
                        ?>
                        <tr class="status-<?php echo $status; ?>">
                            <td><?php echo $coupon['id']; ?></td>
                            <td><strong><code><?php echo clean($coupon['code']); ?></code></strong></td>
                            <td><span class="badge">%<?php echo $coupon['discount']; ?></span></td>
                            <td>
                                <?php echo $coupon['used_count']; ?> /
                                <?php echo $coupon['usage_limit'] ? $coupon['usage_limit'] : '∞'; ?>
                            </td>
                            <td><?php echo formatDate($coupon['expire_date']); ?></td>
                            <td>
                                <?php if ($is_expired): ?>
                                    <span class="status-badge expired">Süresi Doldu</span>
                                <?php elseif ($is_limit_reached): ?>
                                    <span class="status-badge limit">Limit Doldu</span>
                                <?php else: ?>
                                    <span class="status-badge active">Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDate($coupon['created_at']); ?></td>
                            <td>
                                <a href="?edit=<?php echo $coupon['id']; ?>" class="btn-small btn-primary">Düzenle</a>
                                <a href="?delete=<?php echo $coupon['id']; ?>"
                                   class="btn-small btn-danger"
                                   onclick="return confirm('Bu kuponu silmek istediğinizden emin misiniz?')">
                                    Sil
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-results">
                <p>Henüz kupon eklenmemiş. Yukarıdaki formu kullanarak yeni kupon ekleyebilirsiniz.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.add-form-card {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.info-text {
    color: var(--text-light);
    margin-bottom: 1rem;
}

.admin-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: bold;
    color: var(--text-dark);
}

.form-group input,
.form-group select {
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    font-size: 1rem;
}

.form-group small {
    color: var(--text-light);
    font-size: 0.875rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.data-table-card {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
}

.data-table td:last-child {
    white-space: nowrap;
}

.data-table tr:hover {
    background: var(--bg-light);
}

.data-table code {
    background: var(--bg-light);
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}

.badge {
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
}

.status-badge.active {
    background: #10b981;
    color: white;
}

.status-badge.expired {
    background: #ef4444;
    color: white;
}

.status-badge.limit {
    background: #f59e0b;
    color: white;
}

.status-expired {
    opacity: 0.6;
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
}

.btn-primary {
    background: var(--primary-color);
    color: white;
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
}

.btn-secondary:hover {
    background: var(--border-color);
}

.btn-small + .btn-small {
    margin-left: 0.5rem;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
