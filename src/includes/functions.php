<?php

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error'] = "Bu sayfayı görüntülemek için giriş yapmalısınız.";
        header("Location: /auth/login.php");
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        $_SESSION['error'] = "Bu sayfaya erişim yetkiniz yok.";
        header("Location: /index.php");
        exit;
    }
}

function clean($data) {
    if ($data === null || $data === false) {
        return '';
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    return date('d.m.Y H:i', strtotime($date));
}

function getHoursDifference($datetime1, $datetime2) {
    $diff = strtotime($datetime1) - strtotime($datetime2);
    return $diff / 3600; 
}

function setSuccess($message) {
    $_SESSION['success'] = $message;
}

function setError($message) {
    $_SESSION['error'] = $message;
}

function showMessage() {
    if (isset($_SESSION['success'])) {
        echo '<div class="toast-message" data-type="success" data-message="' . clean($_SESSION['success']) . '"></div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="toast-message" data-type="error" data-message="' . clean($_SESSION['error']) . '"></div>';
        unset($_SESSION['error']);
    }
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM User WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Eğer kullanıcı bulunamazsa false yerine null dön
    return $user ? $user : null;
}

function getCities() {
    return [
        'İstanbul',
        'Ankara',
        'İzmir',
        'Antalya',
        'Bursa',
        'Adana',
        'Gaziantep',
        'Konya',
        'Samsun',
        'Kayseri',
        'Eskişehir',
        'Trabzon',
        'Diyarbakır',
        'Erzurum',
        'Van'
    ];
}

function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return '0,00 ₺';
    }
    return number_format((float)$amount, 2, ',', '.') . ' ₺';
}

function validateCoupon($pdo, $code, $company_id = null) {
    $query = "SELECT * FROM Coupons WHERE code = ?";
    $params = [$code];

    if ($company_id) {
        $query .= " AND (company_id = ? OR company_id IS NULL)";
        $params[] = $company_id;
    } else {
        $query .= " AND company_id IS NULL";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        return ['valid' => false, 'message' => 'Geçersiz kupon kodu'];
    }

    // Check expiration date
    if (strtotime($coupon['expire_date']) < time()) {
        return ['valid' => false, 'message' => 'Kupon süresi dolmuş'];
    }

    if ($coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
        return ['valid' => false, 'message' => 'Kupon kullanım limiti doldu'];
    }

    return ['valid' => true, 'discount' => $coupon['discount'], 'coupon_id' => $coupon['id']];
}

function isSeatBooked($pdo, $trip_id, $seat_number) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Booked_Seats 
                          WHERE trip_id = ? AND seat_number = ?");
    $stmt->execute([$trip_id, $seat_number]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

function getBookedSeats($pdo, $trip_id) {
    $stmt = $pdo->prepare("SELECT seat_number FROM Booked_Seats WHERE trip_id = ?");
    $stmt->execute([$trip_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>