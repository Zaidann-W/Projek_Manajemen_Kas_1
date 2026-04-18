<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../config/config.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pw = $_POST['password'] ?? '';

    if(empty($nama) || empty($email) || empty($pw)) {
        $_SESSION['error'] = 'Semua field wajib diisi';
        header("Location: register.php"); exit;
    }

    try {
        $cek = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
        $cek->execute([$email]);
        if($cek->fetch()) {
            $_SESSION['error'] = 'Email sudah terdaftar';
            header("Location: register.php"); exit;
        }

        $stmt = $conn->prepare("INSERT INTO user (nama, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$nama, $email, $pw]);

        $_SESSION['success'] = 'Registrasi berhasil, silahkan login';
        header("Location: login.php"); exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Terjadi kesalahan sistem';
        header("Location: register.php"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar | SmartKas</title>
    <script>document.documentElement.setAttribute('data-theme',localStorage.getItem('smartkas-theme')||'dark');</script>
    <link rel="stylesheet" href="../assets/css/global.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-logo">Smart<span style="color:var(--text-heading)">Kas</span></div>
        <h1>Buat Akun</h1>
        <p class="subtitle">Daftar & mulai kelola keuangan bisnismu</p>

        <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); endif; ?>

        <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama" placeholder="Masukkan nama lengkap" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Masukkan email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Buat password" required>
            </div>
            <button type="submit" class="btn-login">Daftar Sekarang</button>
        </form>

        <p class="footer-text">
            Sudah punya akun? <a href="login.php">Login</a>
        </p>
    </div>
    <script src="../assets/js/theme.js"></script>
</body>
</html>