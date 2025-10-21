<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_firma_admin'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $company_id = intval($_POST['company_id']);

    if (empty($full_name) || empty($email) || empty($password)) {
        setError("Tüm alanları doldurunuz.");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM User WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()['count'] > 0) {
            setError("Bu email adresi zaten kullanılıyor.");
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO User (full_name, email, password, role, company_id, balance) VALUES (?, ?, ?, 'firma_admin', ?, 5000.0)");
            if ($stmt->execute([$full_name, $email, $hashed_password, $company_id])) {
                setSuccess("Firma admin başarıyla eklendi.");
            } else {
                setError("Firma admin eklenirken bir hata oluştu.");
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_firma_admin'])) {
    $id = intval($_POST['id']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $company_id = intval($_POST['company_id']);
    $password = trim($_POST['password']);

    if (empty($full_name) || empty($email)) {
        setError("Ad soyad ve email boş olamaz.");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM User WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()['count'] > 0) {
            setError("Bu email adresi başka bir kullanıcı tarafından kullanılıyor.");
        } else {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE User SET full_name = ?, email = ?, password = ?, company_id = ? WHERE id = ?");
                $result = $stmt->execute([$full_name, $email, $hashed_password, $company_id, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE User SET full_name = ?, email = ?, company_id = ? WHERE id = ?");
                $result = $stmt->execute([$full_name, $email, $company_id, $id]);
            }

            if ($result) {
                setSuccess("Firma admin başarıyla güncellendi.");
            } else {
                setError("Firma admin güncellenirken bir hata oluştu.");
            }
        }
    }
}

// Firma admin silme
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    try {
        $stmt = $pdo->prepare("DELETE FROM User WHERE id = ? AND role = 'firma_admin'");
        $stmt->execute([$id]);
        setSuccess("Firma admin başarıyla silindi.");
    } catch (Exception $e) {
        setError("Firma admin silinirken bir hata oluştu: " . $e->getMessage());
    }

    header("Location: /admin/firma-admins.php");
    exit;
}

// Düzenleme modunu kontrol et
$editing_admin = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM User WHERE id = ? AND role = 'firma_admin'");
    $stmt->execute([$id]);
    $editing_admin = $stmt->fetch();

    // Eğer firma admin bulunamazsa, hata ver ve yönlendir
    if (!$editing_admin) {
        setError("Firma admin bulunamadı.");
        header("Location: /admin/firma-admins.php");
        exit;
    }
}

// Firma adminlerini listele
$stmt = $pdo->query("SELECT u.*, bc.name as company_name
                     FROM User u
                     LEFT JOIN Bus_Company bc ON u.company_id = bc.id
                     WHERE u.role = 'firma_admin'
                     ORDER BY u.created_at DESC");
$firma_admins = $stmt->fetchAll();

// Firmaları çek (dropdown için)
$companies = $pdo->query("SELECT * FROM Bus_Company ORDER BY name")->fetchAll();

$page_title = "Firma Admin Yönetimi";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel">
    <div class="panel-header">
        <h1>Firma Admin Yönetimi</h1>
        <a href="/admin/dashboard.php" class="btn btn-secondary">← Dashboard</a>
    </div>

    <div class="add-form-card">
        <h2><?php echo $editing_admin ? 'Firma Admin Düzenle' : 'Yeni Firma Admin Ekle'; ?></h2>
        <form method="POST" action="" class="admin-form">
            <?php if ($editing_admin): ?>
                <input type="hidden" name="id" value="<?php echo $editing_admin['id']; ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label>Ad Soyad</label>
                    <input type="text" name="full_name" placeholder="Ad Soyad"
                           value="<?php echo $editing_admin ? clean($editing_admin['full_name']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Email"
                           value="<?php echo $editing_admin ? clean($editing_admin['email']) : ''; ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Şifre <?php echo $editing_admin ? '(Boş bırakırsanız değişmez)' : ''; ?></label>
                    <input type="password" name="password" placeholder="Şifre"
                           <?php echo !$editing_admin ? 'required' : ''; ?>>
                </div>

                <div class="form-group">
                    <label>Firma</label>
                    <select name="company_id" required>
                        <option value="">Firma Seçin</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>"
                                    <?php echo ($editing_admin && $editing_admin['company_id'] == $company['id']) ? 'selected' : ''; ?>>
                                <?php echo clean($company['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <?php if ($editing_admin): ?>
                    <button type="submit" name="edit_firma_admin" class="btn btn-primary">Güncelle</button>
                    <a href="/admin/firma-admins.php" class="btn btn-secondary">İptal</a>
                <?php else: ?>
                    <button type="submit" name="add_firma_admin" class="btn btn-primary">Ekle</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="data-table-card">
        <h2>Firma Adminler (<?php echo count($firma_admins); ?>)</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ad Soyad</th>
                    <th>Email</th>
                    <th>Firma</th>
                    <th>Bakiye</th>
                    <th>Kayıt Tarihi</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($firma_admins as $admin): ?>
                    <tr>
                        <td><?php echo $admin['id']; ?></td>
                        <td><strong><?php echo clean($admin['full_name']); ?></strong></td>
                        <td><?php echo clean($admin['email']); ?></td>
                        <td><?php echo clean($admin['company_name'] ?? 'Firma Yok'); ?></td>
                        <td><?php echo formatMoney($admin['balance']); ?></td>
                        <td><?php echo formatDate($admin['created_at']); ?></td>
                        <td>
                            <a href="?edit=<?php echo $admin['id']; ?>" class="btn-small btn-primary">Düzenle</a>
                            <button class="btn-small btn-danger delete-btn"
                                    data-id="<?php echo $admin['id']; ?>"
                                    data-name="<?php echo clean($admin['full_name']); ?>">
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
            <h2>Firma Admini Sil</h2>
        </div>
        <div class="modal-body">
            <p>Bu firma adminini silmek istediğinizden emin misiniz?</p>
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
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    animation: fadeIn 0.3s ease;
}

.modal.show {
    display: flex;
    justify-content: center;
    align-items: center;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.3s ease;
}

.modal-header {
    padding: 2rem 2rem 1rem 2rem;
    text-align: center;
}

.modal-icon {
    font-size: 4rem;
    display: block;
    margin-bottom: 1rem;
}

.modal-header h2 {
    margin: 0;
    color: var(--text-dark);
    font-size: 1.5rem;
}

.modal-body {
    padding: 1rem 2rem 2rem 2rem;
    text-align: center;
}

.modal-body p {
    margin: 0.5rem 0;
    color: var(--text-dark);
}

.item-name-display {
    font-weight: bold;
    font-size: 1.2rem;
    color: var(--primary-color);
    margin: 1rem 0 !important;
}

.warning-text {
    color: #ef4444;
    font-weight: 500;
    margin-top: 1.5rem !important;
    padding: 1rem;
    background: #fee2e2;
    border-radius: 8px;
    border-left: 4px solid #ef4444;
}

.modal-footer {
    padding: 1rem 2rem 2rem 2rem;
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.modal-footer .btn {
    padding: 0.75rem 2rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.modal-footer .btn-secondary {
    background: var(--bg-light);
    color: var(--text-dark);
    border: 1px solid var(--border-color);
}

.modal-footer .btn-secondary:hover {
    background: var(--border-color);
}

.modal-footer .btn-danger {
    background: #ef4444;
    color: white;
}

.modal-footer .btn-danger:hover {
    background: #dc2626;
}

.delete-btn {
    background: #ef4444;
    color: white;
    border: none;
    cursor: pointer;
}

.delete-btn:hover {
    background: #dc2626;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('deleteModal');
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const cancelBtn = modal.querySelector('.cancel-btn');
    const confirmBtn = modal.querySelector('.confirm-delete-btn');
    const itemNameDisplay = modal.querySelector('.item-name-display');

    let deleteId = null;

    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            deleteId = this.getAttribute('data-id');
            const itemName = this.getAttribute('data-name');

            itemNameDisplay.textContent = itemName;
            modal.classList.add('show');
        });
    });

    cancelBtn.addEventListener('click', function() {
        modal.classList.remove('show');
        deleteId = null;
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('show');
            deleteId = null;
        }
    });

    confirmBtn.addEventListener('click', function() {
        if (deleteId) {
            window.location.href = '?delete=' + deleteId;
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) {
            modal.classList.remove('show');
            deleteId = null;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
