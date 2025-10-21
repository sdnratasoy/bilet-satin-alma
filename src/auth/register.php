<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header("Location: /index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    
    if (empty($full_name) || empty($email) || empty($password) || empty($password_confirm)) {
        setError("Lütfen tüm alanları doldurun.");
    } elseif ($password !== $password_confirm) {
        setError("Şifreler eşleşmiyor.");
    } elseif (strlen($password) < 6) {
        setError("Şifre en az 6 karakter olmalıdır.");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM User WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            setError("Bu e-posta adresi zaten kayıtlı.");
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO User (full_name, email, password, role, balance) 
                                  VALUES (?, ?, ?, 'user', 1000.0)");
            
            if ($stmt->execute([$full_name, $email, $hashed_password])) {
                setSuccess("Kayıt başarılı! Giriş yapabilirsiniz.");
                header("Location: /auth/login.php");
                exit;
            } else {
                setError("Kayıt sırasında bir hata oluştu.");
            }
        }
    }
}

$page_title = "Kayıt Ol";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-box">
        <h1><img src="/assets/img/bus-icon.png" alt="Bus" class="bus-icon"> RoadFinder</h1>
        <h2>Kayıt Ol</h2>
        
        <form method="POST" action="" class="auth-form">
            <div class="form-group">
                <label for="full_name">Ad Soyad</label>
                <input type="text" id="full_name" name="full_name" required 
                       value="<?php echo isset($_POST['full_name']) ? clean($_POST['full_name']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="email">E-posta</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($_POST['email']) ? clean($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" required>
                <small>En az 6 karakter</small>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Şifre Tekrar</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Kayıt Ol</button>
        </form>
        
        <div class="auth-links">
            <p>Zaten hesabınız var mı? <a href="/auth/login.php">Giriş Yap</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>