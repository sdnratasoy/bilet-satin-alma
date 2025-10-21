<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header("Location: /index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        setError("Lütfen tüm alanları doldurun.");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM User WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['company_id'] = $user['company_id'];
            
            setSuccess("Hoş geldiniz, " . $user['full_name'] . "!");
            
            if ($user['role'] === 'admin') {
                header("Location: /admin/dashboard.php");
            } elseif ($user['role'] === 'firma_admin') {
                header("Location: /firma-admin/dashboard.php");
            } else {
                header("Location: /index.php");
            }
            exit;
        } else {
            setError("E-posta veya şifre hatalı.");
        }
    }
}

$page_title = "Giriş Yap";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-box">
        <h1><img src="/assets/img/bus-icon.png" alt="Bus" class="bus-icon"> RoadFinder</h1>
        <h2>Giriş Yap</h2>
        
        <form method="POST" action="" class="auth-form">
            <div class="form-group">
                <label for="email">E-posta</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($_POST['email']) ? clean($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Giriş Yap</button>
        </form>
        
        <div class="auth-links">
            <p>Hesabınız yok mu? <a href="/auth/register.php">Kayıt Ol</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>