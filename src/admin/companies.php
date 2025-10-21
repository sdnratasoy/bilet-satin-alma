<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_company'])) {
    $name = trim($_POST['name']);
    
    if (empty($name)) {
        setError("Firma adı boş olamaz.");
    } else {
        $stmt = $pdo->prepare("INSERT INTO Bus_Company (name) VALUES (?)");
        if ($stmt->execute([$name])) {
            setSuccess("Firma başarıyla eklendi.");
        } else {
            setError("Firma eklenirken bir hata oluştu.");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_company'])) {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);

    if (empty($name)) {
        setError("Firma adı boş olamaz.");
    } else {
        $stmt = $pdo->prepare("UPDATE Bus_Company SET name = ? WHERE id = ?");
        if ($stmt->execute([$name, $id])) {
            setSuccess("Firma başarıyla güncellendi.");
        } else {
            setError("Firma güncellenirken bir hata oluştu.");
        }
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    try {
        $stmt = $pdo->prepare("DELETE FROM Bus_Company WHERE id = ?");
        $stmt->execute([$id]);
        setSuccess("Firma başarıyla silindi.");
    } catch (Exception $e) {
        setError("Firma silinirken bir hata oluştu: " . $e->getMessage());
    }

    header("Location: /admin/companies.php");
    exit;
}

$editing_company = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM Bus_Company WHERE id = ?");
    $stmt->execute([$id]);
    $editing_company = $stmt->fetch();

    if (!$editing_company) {
        setError("Firma bulunamadı.");
        header("Location: /admin/companies.php");
        exit;
    }
}

$stmt = $pdo->query("SELECT bc.*, 
                     COUNT(DISTINCT t.id) as trip_count,
                     COUNT(DISTINCT u.id) as admin_count
                     FROM Bus_Company bc
                     LEFT JOIN Trips t ON bc.id = t.company_id
                     LEFT JOIN User u ON bc.id = u.company_id AND u.role = 'firma_admin'
                     GROUP BY bc.id
                     ORDER BY bc.name");
$companies = $stmt->fetchAll();

$page_title = "Firma Yönetimi";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel">
    <div class="panel-header">
        <h1>Firma Yönetimi</h1>
        <a href="/admin/dashboard.php" class="btn btn-secondary">← Dashboard</a>
    </div>
    
    <div class="add-form-card">
        <h2><?php echo $editing_company ? 'Firma Düzenle' : 'Yeni Firma Ekle'; ?></h2>
        <form method="POST" action="" class="inline-form">
            <?php if ($editing_company): ?>
                <input type="hidden" name="id" value="<?php echo $editing_company['id']; ?>">
            <?php endif; ?>
            <div class="form-group">
                <input type="text" name="name" placeholder="Firma Adı"
                       value="<?php echo $editing_company ? clean($editing_company['name']) : ''; ?>" required>
            </div>
            <?php if ($editing_company): ?>
                <button type="submit" name="edit_company" class="btn btn-primary">Güncelle</button>
                <a href="/admin/companies.php" class="btn btn-secondary">İptal</a>
            <?php else: ?>
                <button type="submit" name="add_company" class="btn btn-primary">Ekle</button>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="data-table-card">
        <h2>Firmalar (<?php echo count($companies); ?>)</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Firma Adı</th>
                    <th>Sefer Sayısı</th>
                    <th>Admin Sayısı</th>
                    <th>Kayıt Tarihi</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                    <tr>
                        <td><?php echo $company['id']; ?></td>
                        <td><strong><?php echo clean($company['name']); ?></strong></td>
                        <td><?php echo $company['trip_count']; ?> sefer</td>
                        <td><?php echo $company['admin_count']; ?> admin</td>
                        <td><?php echo formatDate($company['created_at']); ?></td>
                        <td>
                            <a href="?edit=<?php echo $company['id']; ?>" class="btn-small btn-primary">Düzenle</a>
                            <button class="btn-small btn-danger delete-btn"
                                    data-id="<?php echo $company['id']; ?>"
                                    data-name="<?php echo clean($company['name']); ?>">
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
            <h2>Firmayı Sil</h2>
        </div>
        <div class="modal-body">
            <p>Bu firmayı silmek istediğinizden emin misiniz?</p>
            <p class="company-name-display"></p>
            <p class="warning-text">⚠️ Tüm seferleri ve ilişkili veriler kalıcı olarak silinecektir!</p>
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

.inline-form {
    display: flex;
    gap: 1rem;
    align-items: end;
}

.inline-form .form-group {
    flex: 1;
    margin: 0;
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
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
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

.company-name-display {
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
    const companyNameDisplay = modal.querySelector('.company-name-display');

    let deleteId = null;

    // Silme butonlarına tıklandığında
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            deleteId = this.getAttribute('data-id');
            const companyName = this.getAttribute('data-name');

            companyNameDisplay.textContent = companyName;
            modal.classList.add('show');
        });
    });

    // İptal butonuna tıklandığında
    cancelBtn.addEventListener('click', function() {
        modal.classList.remove('show');
        deleteId = null;
    });

    // Modal dışına tıklandığında
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('show');
            deleteId = null;
        }
    });

    // Onay butonuna tıklandığında
    confirmBtn.addEventListener('click', function() {
        if (deleteId) {
            window.location.href = '?delete=' + deleteId;
        }
    });

    // ESC tuşuna basıldığında
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) {
            modal.classList.remove('show');
            deleteId = null;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>