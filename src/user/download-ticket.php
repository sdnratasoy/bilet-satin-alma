<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('user');

$ticket_id = $_GET['id'] ?? 0;
$user = getCurrentUser($pdo);

$stmt = $pdo->prepare("SELECT t.*, tr.*, bc.name as company_name
                       FROM Tickets t
                       JOIN Trips tr ON t.trip_id = tr.id
                       JOIN Bus_Company bc ON tr.company_id = bc.id
                       WHERE t.id = ? AND t.user_id = ?");
$stmt->execute([$ticket_id, $user['id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    die("Bilet bulunamadı.");
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bilet #<?php echo $ticket['id']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }
        .ticket {
            border: 3px solid #667eea;
            border-radius: 10px;
            padding: 30px;
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #ccc;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #667eea;
            margin: 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            padding: 10px;
            background: #f9fafb;
        }
        .label {
            font-weight: bold;
            color: #666;
        }
        .value {
            color: #333;
        }
        .route {
            text-align: center;
            font-size: 24px;
            margin: 30px 0;
            color: #667eea;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px dashed #ccc;
            text-align: center;
            color: #999;
        }
        .print-btn {
            background: #667eea;
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 20px auto;
            display: block;
        }
        @media print {
            .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Yazdır / PDF Olarak Kaydet</button>
    
    <div class="ticket">
        <div class="header">
            <div class="nav-brand">
                <a href="/index.php">
                    <img src="/assets/img/bus-icon.png" alt="RoadFinder" class="brand-icon">
                    RoadFinder
                </a>
            </div>
            <h2>Otobüs Bileti</h2>
            <p>Bilet No: #<?php echo $ticket['id']; ?></p>
        </div>
        
        <div class="row">
            <span class="label">Yolcu Adı:</span>
            <span class="value"><?php echo clean($user['full_name']); ?></span>
        </div>
        
        <div class="row">
            <span class="label">Firma:</span>
            <span class="value"><?php echo clean($ticket['company_name']); ?></span>
        </div>
        
        <div class="route">
            <?php echo clean($ticket['origin_city']); ?> → <?php echo clean($ticket['destination_city']); ?>
        </div>
        
        <div class="row">
            <span class="label">Kalkış Tarihi:</span>
            <span class="value"><?php echo formatDate($ticket['departure_time']); ?></span>
        </div>
        
        <div class="row">
            <span class="label">Varış Tarihi:</span>
            <span class="value"><?php echo formatDate($ticket['arrival_time']); ?></span>
        </div>
        
        <div class="row">
            <span class="label">Koltuk No:</span>
            <span class="value"><?php echo $ticket['seat_number']; ?></span>
        </div>
        
        <div class="row">
            <span class="label">Bilet Ücreti:</span>
            <span class="value"><?php echo formatMoney($ticket['total_price']); ?></span>
        </div>
        
        <div class="row">
            <span class="label">Alım Tarihi:</span>
            <span class="value"><?php echo formatDate($ticket['created_at']); ?></span>
        </div>
        
        <div class="footer">
            <p>İyi yolculuklar dileriz!</p>
            <p><small>Bu bilet <?php echo formatDate(date('Y-m-d H:i:s')); ?> tarihinde oluşturulmuştur.</small></p>
            <p><small>Seyahat sırasında bu bileti ve kimlik belgenizi yanınızda bulundurunuz.</small></p>
        </div>
    </div>
</body>
</html>