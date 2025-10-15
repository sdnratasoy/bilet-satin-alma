<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('user');

$ticket_id = $_GET['id'] ?? 0;
$user = getCurrentUser($pdo);

$stmt = $pdo->prepare("SELECT t.*, tr.departure_time 
                       FROM Tickets t
                       JOIN Trips tr ON t.trip_id = tr.id
                       WHERE t.id = ? AND t.user_id = ?");
$stmt->execute([$ticket_id, $user['id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    setError("Bilet bulunamadı.");
    header("Location: /user/tickets.php");
    exit;
}

if ($ticket['status'] === 'cancelled') {
    setError("Bu bilet zaten iptal edilmiş.");
    header("Location: /user/tickets.php");
    exit;
}

$hours_diff = getHoursDifference($ticket['departure_time'], date('Y-m-d H:i:s'));

if ($hours_diff <= 1) {
    setError("Kalkış saatine 1 saatten az kaldığı için bilet iptal edilemez.");
    header("Location: /user/tickets.php");
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE Tickets SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$ticket_id]);
    
    $stmt = $pdo->prepare("DELETE FROM Booked_Seats WHERE ticket_id = ?");
    $stmt->execute([$ticket_id]);
    
    $stmt = $pdo->prepare("UPDATE User SET balance = balance + ? WHERE id = ?");
    $stmt->execute([$ticket['total_price'], $user['id']]);
    
    $pdo->commit();
    
    setSuccess("Biletiniz başarıyla iptal edildi. " . formatMoney($ticket['total_price']) . " hesabınıza iade edildi.");
} catch (Exception $e) {
    $pdo->rollBack();
    setError("Bilet iptal işleminde bir hata oluştu: " . $e->getMessage());
}

header("Location: /user/tickets.php");
exit;
?>