<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$cities = getCities();
$trips = [];

if (isset($_GET['search'])) {
    $origin = $_GET['origin'] ?? '';
    $destination = $_GET['destination'] ?? '';
    $date = $_GET['date'] ?? '';
    
    $query = "SELECT t.*, bc.name as company_name 
              FROM Trips t 
              JOIN Bus_Company bc ON t.company_id = bc.id 
              WHERE 1=1";
    $params = [];
    
    if (!empty($origin)) {
        $query .= " AND t.origin_city = ?";
        $params[] = $origin;
    }
    
    if (!empty($destination)) {
        $query .= " AND t.destination_city = ?";
        $params[] = $destination;
    }
    
    if (!empty($date)) {
        $query .= " AND DATE(t.departure_time) = DATE(?)";
        $params[] = $date;
    }
    
    $query .= " ORDER BY t.departure_time ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $trips = $stmt->fetchAll();
}

$page_title = "Ana Sayfa";
require_once __DIR__ . '/includes/header.php';
?>

<div class="hero">
    <h1> Yolculuğunuzu Planlayın</h1>
    <p>Türkiye'nin her yerine güvenli ve konforlu seyahat</p>
</div>

<div class="search-section">
    <div class="search-box">
        <h2>Bilet Ara</h2>
        <form method="GET" action="" class="search-form">
            <input type="hidden" name="search" value="1">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="origin">Nereden</label>
                    <select name="origin" id="origin" required>
                        <option value="">Kalkış Noktası Seçin</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo $city; ?>" 
                                    <?php echo (isset($_GET['origin']) && $_GET['origin'] === $city) ? 'selected' : ''; ?>>
                                <?php echo $city; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="destination">Nereye</label>
                    <select name="destination" id="destination" required>
                        <option value="">Varış Noktası Seçin</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo $city; ?>" 
                                    <?php echo (isset($_GET['destination']) && $_GET['destination'] === $city) ? 'selected' : ''; ?>>
                                <?php echo $city; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date">Tarih</label>
                    <div class="date-input-wrapper">
                        <input type="text" name="date" id="date"
                               value="<?php echo isset($_GET['date']) ? clean($_GET['date']) : date('Y-m-d'); ?>"
                               required placeholder="GG.AA.YYYY">
                        <span class="calendar-icon">📅</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary btn-block">Sefer Ara</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_GET['search'])): ?>
    <div class="trips-section">
        <h2>Bulunan Seferler (<?php echo count($trips); ?>)</h2>
        
        <?php if (empty($trips)): ?>
            <div class="no-results">
                <p>Aramanıza uygun sefer bulunamadı.</p>
            </div>
        <?php else: ?>
            <div class="trips-list">
                <?php foreach ($trips as $trip): ?>
                    <?php
                    $booked_seats = getBookedSeats($pdo, $trip['id']);
                    $available_seats = $trip['capacity'] - count($booked_seats);
                    ?>
                    <div class="trip-card">
                        <div class="trip-header">
                            <h3><?php echo clean($trip['company_name']); ?></h3>
                            <span class="trip-price"><?php echo formatMoney($trip['price']); ?></span>
                        </div>
                        
                        <div class="trip-body">
                            <div class="trip-route">
                                <div class="trip-city">
                                    <strong><?php echo clean($trip['origin_city']); ?></strong>
                                    <span><?php echo date('H:i', strtotime($trip['departure_time'])); ?></span>
                                </div>
                                <div class="trip-arrow">→</div>
                                <div class="trip-city">
                                    <strong><?php echo clean($trip['destination_city']); ?></strong>
                                    <span><?php echo date('H:i', strtotime($trip['arrival_time'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="trip-info">
                                <span>📅 <?php echo formatDate($trip['departure_time']); ?></span>
                                <span>💺 <?php echo $available_seats; ?> koltuk</span>
                            </div>
                        </div>
                        
                        <div class="trip-footer">
                            <?php if (isLoggedIn() && hasRole('user')): ?>
                                <?php if ($available_seats > 0): ?>
                                    <a href="/user/buy-ticket.php?trip_id=<?php echo $trip['id']; ?>" 
                                       class="btn btn-primary">Bilet Al</a>
                                <?php else: ?>
                                    <button class="btn btn-disabled" disabled>Dolu</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="/auth/login.php" class="btn btn-primary">Bilet Almak İçin Giriş Yapın</a>
                            <?php endif; ?>
                            
                            <a href="/trip-details.php?id=<?php echo $trip['id']; ?>&origin=<?php echo urlencode($origin); ?>&destination=<?php echo urlencode($destination); ?>&date=<?php echo urlencode($date); ?>"
                               class="btn btn-secondary">Detaylar</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="features-section">
    <h2>Neden RoadFinder?</h2>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">🎫</div>
            <h3>Kolay Bilet Alma</h3>
            <p>Birkaç tıkla biletinizi alın</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">💳</div>
            <h3>Güvenli Ödeme</h3>
            <p>Sanal kredi ile güvenli alışveriş</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">🎟️</div>
            <h3>İndirim Kuponları</h3>
            <p>Avantajlı kampanyalardan yararlanın</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">📱</div>
            <h3>Dijital Bilet</h3>
            <p>PDF biletinizi anında indirin</p>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/tr.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    flatpickr("#date", {
        locale: "tr",
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "d.m.Y",
        minDate: "today",
        defaultDate: "<?php echo isset($_GET['date']) ? clean($_GET['date']) : date('Y-m-d'); ?>",
        inline: false,
        allowInput: true,
        clickOpens: true,
        disableMobile: true,
        position: "below",
        positionElement: undefined,
        onReady: function(selectedDates, dateStr, instance) {
            const calendarIcon = document.querySelector('.calendar-icon');
            calendarIcon.addEventListener('click', function() {
                instance.open();
            });

            instance.calendarContainer.classList.add('calendar-below');
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>