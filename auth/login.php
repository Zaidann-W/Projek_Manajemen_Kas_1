<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../config/config.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pw = $_POST['password'] ?? '';

    if(empty($email) || empty($pw)) {
        $_SESSION['error'] = 'Email dan password wajib diisi';
        header("Location: login.php"); exit;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if($user && $pw === $user['password']) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['nama'];
            header("Location: ../dashboard/index.php"); exit;
        } else {
            if(!$user) {
                $_SESSION['error'] = "Email belum terdaftar, silahkan register terlebih dahulu";
            } else {
                $_SESSION['error'] = "Password salah";
            }
            header('Location: login.php'); exit;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Terjadi kesalahan sistem';
        header('Location: login.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SmartKas</title>
    <script>document.documentElement.setAttribute('data-theme',localStorage.getItem('smartkas-theme')||'dark');</script>
    <link rel="stylesheet" href="../assets/css/global.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-logo">Smart<span style="color:var(--text-heading)">Kas</span></div>
        <h1>Selamat Datang</h1>
        <p class="subtitle">Masuk ke akun kamu untuk melanjutkan</p>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Masukkan email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn-login">Masuk</button>
        </form>

        <p class="footer-text">
            Belum punya akun? <a href="register.php">Daftar sekarang</a>
        </p>
    </div>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
