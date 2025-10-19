<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$current_user = getCurrentUser($pdo);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - RoadFinder' : 'RoadFinder - Bilet Satın Alma Platformu'; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="/index.php">
                    <img src="/assets/img/bus-icon.png" alt="RoadFinder" class="brand-icon">
                    RoadFinder
                </a>
            </div>
            <ul class="nav-menu">
                <li><a href="/index.php">Ana Sayfa</a></li>
                
                <?php if (isLoggedIn()): ?>
                    <?php if (hasRole('admin')): ?>
                        <li><a href="/admin/dashboard.php">Admin Panel</a></li>
                    <?php elseif (hasRole('firma_admin')): ?>
                        <li><a href="/firma-admin/dashboard.php">Firma Panel</a></li>
                    <?php else: ?>
                        <li><a href="/user/tickets.php">Biletlerim</a></li>
                    <?php endif; ?>
                    
                    <li class="user-info">
                        <span>👤 <?php echo $current_user ? clean($current_user['full_name']) : ''; ?></span>
                        <?php if (hasRole('user') && $current_user): ?>
                            <span class="balance">💳 <?php echo formatMoney($current_user['balance']); ?></span>
                        <?php endif; ?>
                    </li>
                    <li><a href="/auth/logout.php" class="btn-logout">Çıkış</a></li>
                <?php else: ?>
                    <li><a href="/auth/login.php" class="btn-login">Giriş Yap</a></li>
                    <li><a href="/auth/register.php" class="btn-register">Kayıt Ol</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    
    <main class="main-content">
        <div class="container">
            <?php showMessage(); ?>