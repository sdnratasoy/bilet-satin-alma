<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('firma_admin');

$company_id = $_SESSION['company_id'];

$stmt = $pdo->prepare("SELECT * FROM Bus_Company WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_trip'])) {
    $origin_city = trim($_POST['origin_city']);
    $destination_city = trim($_POST['destination_city']);
    $departure_time = $_POST['departure_time'];
    $arrival_time = $_POST['arrival_time'];
    $price = floatval($_POST['price']);
    $capacity = intval($_POST['capacity']);

    if (empty($origin_city) || empty($destination_city) || empty($departure_time) || empty($arrival_time) || empty($price) || empty($capacity)) {
        setError("Tüm alanları doldurunuz.");
    } elseif ($origin_city === $destination_city) {
        setError("Kalkış ve varış şehri aynı olamaz.");
    } elseif (strtotime($departure_time) >= strtotime($arrival_time)) {
        setError("Varış zamanı, kalkış zamanından sonra olmalıdır.");
    } elseif ($price <= 0) {
        setError("Fiyat 0'dan büyük olmalıdır.");
    } elseif ($capacity <= 0 || $capacity > 60) {
        setError("Kapasite 1-60 arasında olmalıdır.");
    } else {
        $stmt = $pdo->prepare("INSERT INTO Trips (company_id, origin_city, destination_city, departure_time, arrival_time, price, capacity)
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$company_id, $origin_city, $destination_city, $departure_time, $arrival_time, $price, $capacity])) {
            setSuccess("Sefer başarıyla eklendi.");
        } else {
            setError("Sefer eklenirken bir hata oluştu.");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_trip'])) {
    $id = intval($_POST['id']);
    $origin_city = trim($_POST['origin_city']);
    $destination_city = trim($_POST['destination_city']);
    $departure_time = $_POST['departure_time'];
    $arrival_time = $_POST['arrival_time'];
    $price = floatval($_POST['price']);
    $capacity = intval($_POST['capacity']);

    if (empty($origin_city) || empty($destination_city) || empty($departure_time) || empty($arrival_time) || empty($price) || empty($capacity)) {
        setError("Tüm alanları doldurunuz.");
    } elseif ($origin_city === $destination_city) {
        setError("Kalkış ve varış şehri aynı olamaz.");
    } elseif (strtotime($departure_time) >= strtotime($arrival_time)) {
        setError("Varış zamanı, kalkış zamanından sonra olmalıdır.");
    } elseif ($price <= 0) {
        setError("Fiyat 0'dan büyük olmalıdır.");
    } elseif ($capacity <= 0 || $capacity > 60) {
        setError("Kapasite 1-60 arasında olmalıdır.");
    } else {
        $stmt = $pdo->prepare("SELECT company_id FROM Trips WHERE id = ?");
        $stmt->execute([$id]);
        $trip = $stmt->fetch();

        if ($trip && $trip['company_id'] == $company_id) {
            $stmt = $pdo->prepare("UPDATE Trips SET origin_city = ?, destination_city = ?, departure_time = ?, arrival_time = ?, price = ?, capacity = ? WHERE id = ?");
            if ($stmt->execute([$origin_city, $destination_city, $departure_time, $arrival_time, $price, $capacity, $id])) {
                setSuccess("Sefer başarıyla güncellendi.");
            } else {
                setError("Sefer güncellenirken bir hata oluştu.");
            }
        } else {
            setError("Bu seferi düzenleme yetkiniz yok.");
        }
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $stmt = $pdo->prepare("SELECT company_id FROM Trips WHERE id = ?");
    $stmt->execute([$id]);
    $trip = $stmt->fetch();

    if ($trip && $trip['company_id'] == $company_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM Trips WHERE id = ?");
            $stmt->execute([$id]);
            setSuccess("Sefer başarıyla silindi.");
        } catch (Exception $e) {
            setError("Sefer silinirken bir hata oluştu: " . $e->getMessage());
        }
    } else {
        setError("Bu seferi silme yetkiniz yok.");
    }

    header("Location: /firma-admin/trips.php");
    exit;
}

$editing_trip = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM Trips WHERE id = ? AND company_id = ?");
    $stmt->execute([$id, $company_id]);
    $editing_trip = $stmt->fetch();

    if (!$editing_trip) {
        setError("Bu seferi düzenleme yetkiniz yok veya sefer bulunamadı.");
        header("Location: /firma-admin/trips.php");
        exit;
    }
}

$stmt = $pdo->prepare("SELECT t.*,
                      (SELECT COUNT(*) FROM Booked_Seats WHERE trip_id = t.id) as booked_count
                      FROM Trips t
                      WHERE t.company_id = ?
                      ORDER BY t.departure_time DESC");
$stmt->execute([$company_id]);
$trips = $stmt->fetchAll();

$cities = getCities();

$page_title = "Sefer Yönetimi";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel">
    <div class="panel-header">
        <h1>Sefer Yönetimi</h1>
        <a href="/firma-admin/dashboard.php" class="btn btn-secondary">← Dashboard</a>
    </div>

    <div class="add-form-card">
        <h2><?php echo $editing_trip ? 'Sefer Düzenle' : 'Yeni Sefer Ekle'; ?></h2>
        <p class="info-text">Firma: <strong><?php echo clean($company['name']); ?></strong></p>
        <form method="POST" action="" class="admin-form">
            <?php if ($editing_trip): ?>
                <input type="hidden" name="id" value="<?php echo $editing_trip['id']; ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label>Kalkış Şehri</label>
                    <select name="origin_city" required>
                        <option value="">Şehir Seçin</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo $city; ?>"
                                    <?php echo ($editing_trip && $editing_trip['origin_city'] == $city) ? 'selected' : ''; ?>>
                                <?php echo $city; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Varış Şehri</label>
                    <select name="destination_city" required>
                        <option value="">Şehir Seçin</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo $city; ?>"
                                    <?php echo ($editing_trip && $editing_trip['destination_city'] == $city) ? 'selected' : ''; ?>>
                                <?php echo $city; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Kalkış Zamanı</label>
                    <input type="datetime-local" name="departure_time"
                           value="<?php echo $editing_trip ? date('Y-m-d\TH:i', strtotime($editing_trip['departure_time'])) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Varış Zamanı</label>
                    <input type="datetime-local" name="arrival_time"
                           value="<?php echo $editing_trip ? date('Y-m-d\TH:i', strtotime($editing_trip['arrival_time'])) : ''; ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Fiyat (₺)</label>
                    <input type="number" name="price" placeholder="250.00" min="0.01" step="0.01"
                           value="<?php echo $editing_trip ? $editing_trip['price'] : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Kapasite (Koltuk Sayısı)</label>
                    <input type="number" name="capacity" placeholder="40" min="1" max="60"
                           value="<?php echo $editing_trip ? $editing_trip['capacity'] : '40'; ?>" required>
                    <small>Maksimum 60 koltuk</small>
                </div>
            </div>

            <div class="form-actions">
                <?php if ($editing_trip): ?>
                    <button type="submit" name="edit_trip" class="btn btn-primary">Güncelle</button>
                    <a href="/firma-admin/trips.php" class="btn btn-secondary">İptal</a>
                <?php else: ?>
                    <button type="submit" name="add_trip" class="btn btn-primary">Ekle</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="data-table-card">
        <h2>Seferler (<?php echo count($trips); ?>)</h2>
        <?php if (count($trips) > 0): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Güzergah</th>
                            <th>Kalkış</th>
                            <th>Varış</th>
                            <th>Süre</th>
                            <th>Fiyat</th>
                            <th>Doluluk</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $trip): ?>
                            <?php
                            $available_seats = $trip['capacity'] - $trip['booked_count'];
                            $occupancy_rate = ($trip['booked_count'] / $trip['capacity']) * 100;
                            $is_past = strtotime($trip['departure_time']) < time();
                            $duration = getHoursDifference($trip['arrival_time'], $trip['departure_time']);
                            ?>
                            <tr class="<?php echo $is_past ? 'trip-past' : ''; ?>">
                                <td><?php echo $trip['id']; ?></td>
                                <td>
                                    <div class="route">
                                        <span class="city"><?php echo clean($trip['origin_city']); ?></span>
                                        <span class="arrow">→</span>
                                        <span class="city"><?php echo clean($trip['destination_city']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo formatDate($trip['departure_time']); ?></td>
                                <td><?php echo formatDate($trip['arrival_time']); ?></td>
                                <td><?php echo round($duration, 1); ?> saat</td>
                                <td><strong><?php echo formatMoney($trip['price']); ?></strong></td>
                                <td>
                                    <div class="occupancy">
                                        <div class="occupancy-bar">
                                            <div class="occupancy-fill" style="width: <?php echo $occupancy_rate; ?>%"></div>
                                        </div>
                                        <span class="occupancy-text">
                                            <?php echo $trip['booked_count']; ?>/<?php echo $trip['capacity']; ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($is_past): ?>
                                        <span class="status-badge past">Geçmiş</span>
                                    <?php elseif ($available_seats == 0): ?>
                                        <span class="status-badge full">Dolu</span>
                                    <?php elseif ($available_seats < 5): ?>
                                        <span class="status-badge few">Az Koltuk</span>
                                    <?php else: ?>
                                        <span class="status-badge available">Müsait</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?edit=<?php echo $trip['id']; ?>" class="btn-small btn-primary">Düzenle</a>
                                    <button class="btn-small btn-danger delete-btn"
                                            data-id="<?php echo $trip['id']; ?>"
                                            data-name="<?php echo clean($trip['origin_city']) . ' → ' . clean($trip['destination_city']); ?>">
                                        Sil
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-results">
                <p>Henüz sefer eklenmemiş. Yukarıdaki formu kullanarak yeni sefer ekleyebilirsiniz.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-icon">⚠️</span>
            <h2>Seferi Sil</h2>
        </div>
        <div class="modal-body">
            <p>Bu seferi silmek istediğinizden emin misiniz?</p>
            <p class="item-name-display"></p>
            <p class="warning-text">⚠️ Tüm rezervasyonlar iptal edilecektir! Bu işlem geri alınamaz!</p>
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

.data-table tr.trip-past {
    opacity: 0.5;
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

.occupancy {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.occupancy-bar {
    flex: 1;
    height: 8px;
    background: var(--bg-light);
    border-radius: 10px;
    overflow: hidden;
    min-width: 60px;
}

.occupancy-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    transition: width 0.3s;
}

.occupancy-text {
    font-size: 0.875rem;
    white-space: nowrap;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: bold;
    display: inline-block;
    white-space: nowrap;
}

.status-badge.available {
    background: #10b981;
    color: white;
}

.status-badge.few {
    background: #f59e0b;
    color: white;
}

.status-badge.full {
    background: #ef4444;
    color: white;
}

.status-badge.past {
    background: #6b7280;
    color: white;
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

/* Modal Styles */
#deleteModal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0 !important;
    top: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    animation: fadeIn 0.3s ease;
}

#deleteModal.show {
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
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

#deleteModal .modal-content {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    margin: auto !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.3s ease;
    position: relative !important;
    left: auto !important;
    right: auto !important;
    top: auto !important;
    bottom: auto !important;
}

#deleteModal .modal-header {
    padding: 2rem 2rem 1rem 2rem;
    text-align: center;
}

#deleteModal .modal-icon {
    font-size: 4rem;
    display: block;
    margin-bottom: 1rem;
}

#deleteModal .modal-header h2 {
    margin: 0;
    color: var(--text-dark);
    font-size: 1.5rem;
}

#deleteModal .modal-body {
    padding: 1rem 2rem 2rem 2rem;
    text-align: center;
}

#deleteModal .modal-body p {
    margin: 0.5rem 0;
    color: var(--text-dark);
}

#deleteModal .item-name-display {
    font-weight: bold;
    font-size: 1.2rem;
    color: var(--primary-color);
    margin: 1rem 0 !important;
}

#deleteModal .warning-text {
    color: #ef4444;
    font-weight: 500;
    margin-top: 1.5rem !important;
    padding: 1rem;
    background: #fee2e2;
    border-radius: 8px;
    border-left: 4px solid #ef4444;
}

#deleteModal .modal-footer {
    padding: 1rem 2rem 2rem 2rem;
    display: flex;
    gap: 1rem;
    justify-content: center;
}

#deleteModal .modal-footer .btn {
    padding: 0.75rem 2rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

#deleteModal .modal-footer .btn-secondary {
    background: var(--bg-light);
    color: var(--text-dark);
    border: 1px solid var(--border-color);
}

#deleteModal .modal-footer .btn-secondary:hover {
    background: var(--border-color);
}

#deleteModal .modal-footer .btn-danger {
    background: #ef4444;
    color: white;
}

#deleteModal .modal-footer .btn-danger:hover {
    background: #dc2626;
}

.delete-btn {
    background: #ef4444;
    color: white;
    border: none;
    cursor: pointer;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    border-radius: 5px;
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

            // Modal'ı göster ve inline style ile zorla ortala
            modal.classList.add('show');
            modal.style.display = 'flex';
            modal.style.justifyContent = 'center';
            modal.style.alignItems = 'center';
            modal.style.position = 'fixed';
            modal.style.left = '0';
            modal.style.top = '0';
            modal.style.right = '0';
            modal.style.bottom = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.zIndex = '10000';
        });
    });

    cancelBtn.addEventListener('click', function() {
        modal.classList.remove('show');
        modal.style.display = 'none';
        deleteId = null;
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
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
            modal.style.display = 'none';
            deleteId = null;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
