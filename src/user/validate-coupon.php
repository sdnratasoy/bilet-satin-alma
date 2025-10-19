<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['valid' => false, 'message' => 'Oturum açmanız gerekiyor.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$coupon_code = trim($_POST['coupon_code'] ?? '');
$company_id = intval($_POST['company_id'] ?? 0);

if (empty($coupon_code)) {
    echo json_encode(['valid' => false, 'message' => 'Kupon kodu boş olamaz.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = validateCoupon($pdo, $coupon_code, $company_id);

if ($result['valid']) {
    echo json_encode([
        'valid' => true,
        'discount' => floatval($result['discount']),
        'message' => 'Kupon geçerli.'
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'valid' => false,
        'message' => $result['message']
    ], JSON_UNESCAPED_UNICODE);
}
?>
