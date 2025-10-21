<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$filter_company = isset($_GET['company']) ? intval($_GET['company']) : 0;
$filter_origin = isset($_GET['origin']) ? $_GET['origin'] : '';
$filter_destination = isset($_GET['destination']) ? $_GET['destination'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

$query = "SELECT t.*, bc.name as company_name,
          (SELECT COUNT(*) FROM Booked_Seats WHERE trip_id = t.id) as booked_count
          FROM Trips t
          JOIN Bus_Company bc ON t.company_id = bc.id
          WHERE 1=1";

$params = [];

if ($filter_company > 0) {
    $query .= " AND t.company_id = ?";
    $params[] = $filter_company;
}

if (!empty($filter_origin)) {
    $query .= " AND t.origin_city = ?";
    $params[] = $filter_origin;
}

if (!empty($filter_destination)) {
    $query .= " AND t.destination_city = ?";
    $params[] = $filter_destination;
}

if (!empty($filter_date)) {
    $query .= " AND DATE(t.departure_time) = ?";
    $params[] = $filter_date;
}

$query .= " ORDER BY t.departure_time DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$trips = $stmt->fetchAll();

$companies = $pdo->query("SELECT * FROM Bus_Company ORDER BY name")->fetchAll();

$cities = getCities();

$page_title = "Tüm Seferler";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel">
    <div class="panel-header">
        <h1>Tüm Seferler</h1>
        <a href="/admin/dashboard.php" class="btn btn-secondary">← Dashboard</a>
    </div>

    <div class="filter-card">
        <h2>Filtrele</h2>
        <form method="GET" action="" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label>Firma</label>
                    <select name="company">
                        <option value="0">Tüm Firmalar</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>"
                                    <?php echo $filter_company == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo clean($company['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Kalkış Şehri</label>
                    <select name="origin">
                        <option value="">Tümü</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo $city; ?>"
                                    <?php echo $filter_origin == $city ? 'selected' : ''; ?>>
                                <?php echo $city; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Varış Şehri</label>
                    <select name="destination">
                        <option value="">Tümü</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo $city; ?>"
                                    <?php echo $filter_destination == $city ? 'selected' : ''; ?>>
                                <?php echo $city; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tarih</label>
                    <input type="date" name="date" value="<?php echo $filter_date; ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Filtrele</button>
                <a href="/admin/all-trips.php" class="btn btn-secondary">Temizle</a>
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
                            <th>Firma</th>
                            <th>Güzergah</th>
                            <th>Kalkış</th>
                            <th>Varış</th>
                            <th>Süre</th>
                            <th>Fiyat</th>
                            <th>Doluluk</th>
                            <th>Durum</th>
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
                                <td><strong><?php echo clean($trip['company_name']); ?></strong></td>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-results">
                <p>Seçilen filtrelere uygun sefer bulunamadı.</p>
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

.filter-card {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.filter-form {
    margin-top: 1rem;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: bold;
    color: var(--text-dark);
    font-size: 0.875rem;
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

.btn-primary {
    background: var(--primary-color);
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1rem;
}

.btn-primary:hover {
    background: var(--secondary-color);
}

.btn-secondary {
    background: var(--bg-light);
    color: var(--text-dark);
    padding: 0.75rem 1.5rem;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    text-decoration: none;
    display: inline-block;
    font-size: 1rem;
}

.btn-secondary:hover {
    background: var(--border-color);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
