<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

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
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Coupons WHERE code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()['count'] > 0) {
            setError("Bu kupon kodu zaten kullanılıyor.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO Coupons (code, discount, company_id, usage_limit, expire_date) VALUES (?, ?, NULL, ?, ?)");
            if ($stmt->execute([$code, $discount, $usage_limit, $expire_date])) {
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
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Coupons WHERE code = ? AND id != ?");
        $stmt->execute([$code, $id]);
        if ($stmt->fetch()['count'] > 0) {
            setError("Bu kupon kodu başka bir kupon tarafından kullanılıyor.");
        } else {
            $stmt = $pdo->prepare("UPDATE Coupons SET code = ?, discount = ?, usage_limit = ?, expire_date = ? WHERE id = ?");
            if ($stmt->execute([$code, $discount, $usage_limit, $expire_date, $id])) {
                setSuccess("Kupon başarıyla güncellendi.");
            } else {
                setError("Kupon güncellenirken bir hata oluştu.");
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    try {
        $stmt = $pdo->prepare("DELETE FROM Coupons WHERE id = ?");
        $stmt->execute([$id]);
        setSuccess("Kupon başarıyla silindi.");
    } catch (Exception $e) {
        setError("Kupon silinirken bir hata oluştu: " . $e->getMessage());
    }

    header("Location: /admin/coupons.php");
    exit;
}

$editing_coupon = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM Coupons WHERE id = ? AND company_id IS NULL");
    $stmt->execute([$id]);
    $editing_coupon = $stmt->fetch();

    if (!$editing_coupon) {
        setError("Genel kupon bulunamadı.");
        header("Location: /admin/coupons.php");
        exit;
    }
}

$stmt = $pdo->query("SELECT * FROM Coupons WHERE company_id IS NULL ORDER BY created_at DESC");
$coupons = $stmt->fetchAll();

$page_title = "Kupon Yönetimi";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel">
    <div class="panel-header">
        <h1>Genel Kupon Yönetimi</h1>
        <a href="/admin/dashboard.php" class="btn btn-secondary">← Dashboard</a>
    </div>

    <div class="add-form-card">
        <h2><?php echo $editing_coupon ? 'Kupon Düzenle' : 'Yeni Genel Kupon Ekle'; ?></h2>
        <p class="info-text">Bu kuponlar tüm firmalar için geçerlidir.</p>
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
                    <a href="/admin/coupons.php" class="btn btn-secondary">İptal</a>
                <?php else: ?>
                    <button type="submit" name="add_coupon" class="btn btn-primary">Ekle</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="data-table-card">
        <h2>Genel Kuponlar (<?php echo count($coupons); ?>)</h2>
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
                            <button class="btn-small btn-danger delete-btn"
                                    data-id="<?php echo $coupon['id']; ?>"
                                    data-name="<?php echo clean($coupon['code']); ?>">
                                Sil
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-icon">⚠️</span>
            <h2>Kuponu Sil</h2>
        </div>
        <div class="modal-body">
            <p>Bu kuponu silmek istediğinizden emin misiniz?</p>
            <p class="item-name-display"></p>
            <p class="warning-text">⚠️ Bu işlem geri alınamaz!</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary cancel-btn">İptal</button>
            <button class="btn btn-danger confirm-delete-btn">Evet, Sil</button>
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

.btn-small + .btn-small {
    margin-left: 0.5rem;
}

/* Modal Styles */
.modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);backdrop-filter:blur(4px);animation:fadeIn 0.3s ease}.modal.show{display:flex;justify-content:center;align-items:center}@keyframes fadeIn{from{opacity:0}to{opacity:1}}@keyframes slideIn{from{transform:translateY(-50px);opacity:0}to{transform:translateY(0);opacity:1}}.modal-content{background:white;border-radius:12px;max-width:500px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:slideIn 0.3s ease}.modal-header{padding:2rem 2rem 1rem 2rem;text-align:center}.modal-icon{font-size:4rem;display:block;margin-bottom:1rem}.modal-header h2{margin:0;color:var(--text-dark);font-size:1.5rem}.modal-body{padding:1rem 2rem 2rem 2rem;text-align:center}.modal-body p{margin:0.5rem 0;color:var(--text-dark)}.item-name-display{font-weight:bold;font-size:1.2rem;color:var(--primary-color);margin:1rem 0!important}.warning-text{color:#ef4444;font-weight:500;margin-top:1.5rem!important;padding:1rem;background:#fee2e2;border-radius:8px;border-left:4px solid #ef4444}.modal-footer{padding:1rem 2rem 2rem 2rem;display:flex;gap:1rem;justify-content:center}.modal-footer .btn{padding:0.75rem 2rem;border:none;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;transition:all 0.2s}.modal-footer .btn-secondary{background:var(--bg-light);color:var(--text-dark);border:1px solid var(--border-color)}.modal-footer .btn-secondary:hover{background:var(--border-color)}.modal-footer .btn-danger{background:#ef4444;color:white}.modal-footer .btn-danger:hover{background:#dc2626}.delete-btn{background:#ef4444;color:white;border:none;cursor:pointer}.delete-btn:hover{background:#dc2626}
</style>

<script>
document.addEventListener('DOMContentLoaded',function(){const modal=document.getElementById('deleteModal');const deleteButtons=document.querySelectorAll('.delete-btn');const cancelBtn=modal.querySelector('.cancel-btn');const confirmBtn=modal.querySelector('.confirm-delete-btn');const itemNameDisplay=modal.querySelector('.item-name-display');let deleteId=null;deleteButtons.forEach(button=>{button.addEventListener('click',function(){deleteId=this.getAttribute('data-id');const itemName=this.getAttribute('data-name');itemNameDisplay.textContent=itemName;modal.classList.add('show')})});cancelBtn.addEventListener('click',function(){modal.classList.remove('show');deleteId=null});modal.addEventListener('click',function(e){if(e.target===modal){modal.classList.remove('show');deleteId=null}});confirmBtn.addEventListener('click',function(){if(deleteId){window.location.href='?delete='+deleteId}});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal.classList.contains('show')){modal.classList.remove('show');deleteId=null}})});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
