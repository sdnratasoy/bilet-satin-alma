<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

// Firma ekleme
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

// Firma silme
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

// Firmaları listele
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
        <h2>Yeni Firma Ekle</h2>
        <form method="POST" action="" class="inline-form">
            <div class="form-group">
                <input type="text" name="name" placeholder="Firma Adı" required>
            </div>
            <button type="submit" name="add_company" class="btn btn-primary">Ekle</button>
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
                            <a href="?delete=<?php echo $company['id']; ?>" 
                               class="btn-small btn-danger"
                               onclick="return confirm('Bu firmayı silmek istediğinizden emin misiniz? Tüm seferleri ve ilişkili veriler silinecektir!')">
                                Sil
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>