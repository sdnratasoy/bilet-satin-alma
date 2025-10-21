<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('user');

$user = getCurrentUser($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
} else {
    die("Geçersiz istek. Bu sayfaya doğrudan erişilemez.");
}

if ($ticket_id <= 0) {
    die("Geçersiz bilet. Bilet ID bulunamadı.");
}

if (!$user || !isset($user['id'])) {
    die("Kullanıcı bilgisi alınamadı. Lütfen tekrar giriş yapın.");
}

$stmt = $pdo->prepare("SELECT t.id as ticket_id, t.seat_number, t.total_price, t.status, t.created_at,
                       tr.origin_city, tr.destination_city, tr.departure_time, tr.arrival_time,
                       bc.name as company_name
                       FROM Tickets t
                       JOIN Trips tr ON t.trip_id = tr.id
                       JOIN Bus_Company bc ON tr.company_id = bc.id
                       WHERE t.id = ? AND t.user_id = ?");
$stmt->execute([$ticket_id, $user['id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    die("Bilet bulunamadı. Lütfen biletlerinizi kontrol edin.");
}

$stmt = $pdo->prepare("SELECT COUNT(*) as ticket_number
                       FROM Tickets
                       WHERE user_id = ? AND created_at <= ?");
$stmt->execute([$user['id'], $ticket['created_at']]);
$ticket_number_result = $stmt->fetch();
$user_ticket_number = $ticket_number_result['ticket_number'];

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bilet #<?php echo $user_ticket_number; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
        }
        
        .ticket {
            border: 3px solid #3b82f6;
            border-radius: 15px;
            padding: 30px;
            background: white;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px dashed #cbd5e1;
            padding-bottom: 25px;
            margin-bottom: 25px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .logo-container img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        .logo-container .brand-text {
            color: #0f172a;
            font-size: 2.2rem;
            font-weight: bold;
        }

        .header h1 {
            color: #3b82f6;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header h2 {
            color: #64748b;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        .header .ticket-id {
            background: #3b82f6;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            display: inline-block;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .label {
            font-weight: bold;
            color: #475569;
        }
        
        .value {
            color: #0f172a;
            font-weight: 500;
        }
        
        .route-section {
            text-align: center;
            margin: 30px 0;
            padding: 25px;
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            border-radius: 12px;
            color: white;
        }
        
        .route {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .route-arrow {
            font-size: 2.5rem;
            margin: 0 15px;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 3px dashed #cbd5e1;
            text-align: center;
            color: #64748b;
        }
        
        .footer p {
            margin: 8px 0;
        }
        
        .print-btn {
            background: #3b82f6;
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin: 20px auto;
            display: block;
            transition: all 0.3s;
        }
        
        .print-btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .print-btn {
                display: none;
            }
            
            .ticket {
                border: 2px solid #000;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="print-btn" onclick="window.print()">🖨️ Yazdır / PDF Olarak Kaydet</button>
        
        <div class="ticket">
            <div class="header">
                <div class="logo-container">
                    <img src="/assets/img/bus-icon.png" alt="RoadFinder">
                    <span class="brand-text">RoadFinder</span>
                </div>
                <h2>Otobüs Bileti</h2>
                <div class="ticket-id">Bilet No: #<?php echo $user_ticket_number; ?></div>
            </div>
            
            <div class="info-row">
                <span class="label">👤 Yolcu Adı:</span>
                <span class="value"><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            
            <div class="info-row">
                <span class="label">🚌 Firma:</span>
                <span class="value"><?php echo htmlspecialchars($ticket['company_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            
            <div class="route-section">
                <div class="route">
                    <?php echo htmlspecialchars($ticket['origin_city'], ENT_QUOTES, 'UTF-8'); ?>
                    <span class="route-arrow">→</span>
                    <?php echo htmlspecialchars($ticket['destination_city'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
            
            <div class="info-row">
                <span class="label">📅 Kalkış Tarihi ve Saati:</span>
                <span class="value"><?php echo date('d.m.Y H:i', strtotime($ticket['departure_time'])); ?></span>
            </div>
            
            <div class="info-row">
                <span class="label">🏁 Varış Tarihi ve Saati:</span>
                <span class="value"><?php echo date('d.m.Y H:i', strtotime($ticket['arrival_time'])); ?></span>
            </div>
            
            <div class="info-row">
                <span class="label">💺 Koltuk Numarası:</span>
                <span class="value"><?php echo $ticket['seat_number']; ?></span>
            </div>
            
            <div class="info-row">
                <span class="label">💰 Bilet Ücreti:</span>
                <span class="value"><?php echo number_format($ticket['total_price'], 2, ',', '.') . ' ₺'; ?></span>
            </div>
            
            <div class="info-row">
                <span class="label">🕐 Alım Tarihi:</span>
                <span class="value"><?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?></span>
            </div>
            
            <div class="footer">
                <p style="font-size: 1.2rem; font-weight: bold; color: #3b82f6;">✨ İyi yolculuklar dileriz! ✨</p>
                <p style="margin-top: 15px;">Bu bilet <?php echo date('d.m.Y H:i', time()); ?> tarihinde oluşturulmuştur.</p>
                <p>Seyahat sırasında bu bileti ve kimlik belgenizi yanınızda bulundurunuz.</p>
                <p style="margin-top: 15px; font-style: italic;">RoadFinder - Güvenli ve Kolay Bilet Satın Alma Platformu</p>
            </div>
        </div>
    </div>
</body>
</html>