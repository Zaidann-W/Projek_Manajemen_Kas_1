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
        header("Location: register.php");
        exit;
    }

    try {
        // cek apakah email sudah terdaftar
        $cek = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
        $cek->execute([$email]);

        if($cek->fetch()) {
            $_SESSION['error'] = 'Email sudah terdaftar';
            header("Location: register.php");
            exit;
        }

        // insert user
        $stmt = $conn->prepare("INSERT INTO user (nama, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$nama, $email, $pw]);

        $_SESSION['success'] = 'Registrasi berhasil, silahkan login';
        header("Location: login.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Terjadi kesalahan sistem';
        header("Location: register.php");
        exit;
    }
}

?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Daftar UMKM</title>

    <style>
    * {
        box-sizing: border-box;
        font-family: 'Segoe UI', sans-serif;
    }

    body {
        background: linear-gradient(120deg, #f6f9ff, #eef2ff);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .card {
        background: #fff;
        width: 100%;
        max-width: 420px;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, .08);
    }

    .card h2 {
        margin-bottom: 5px;
        text-align: center;
    }

    .card p {
        text-align: center;
        color: #666;
        margin-bottom: 25px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    label {
        font-size: 14px;
        color: #333;
    }

    input {
        width: 100%;
        padding: 10px 12px;
        margin-top: 6px;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 14px;
    }

    input:focus {
        border-color: #6366f1;
        outline: none;
    }

    button {
        width: 100%;
        padding: 12px;
        background: #6366f1;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 15px;
        cursor: pointer;
        margin-top: 10px;
    }

    button:hover {
        background: #4f46e5;
    }

    .error {
        background: #fee2e2;
        color: #991b1b;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 15px;
        text-align: center;
        font-size: 14px;
    }

    .footer {
        text-align: center;
        margin-top: 15px;
        font-size: 14px;
    }

    .footer a {
        color: #6366f1;
        text-decoration: none;
    }
    </style>
</head>

<body>

    <div class="card">
        <h2>Daftar UMKM</h2>
        <p>Buat akun & mulai kelola keuangan bisnismu</p>

        <?php if(isset($_SESSION['error'])) : ?>
        <div class="error"><?= $_SESSION['error']; ?></div>
        <?php unset($_SESSION['error']); endif; ?>

        <?php if(isset($_SESSION['success'])) : ?>
        <div
            style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 8px; margin-bottom: 15px; text-align: center; font-size: 14px;">
            <?= $_SESSION['success']; ?>
        </div>
        <?php unset($_SESSION['success']); endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit">Daftar</button>
        </form>

        <div class="footer">
            Sudah punya akun? <a href="login.php">Login</a>
        </div>
    </div>

</body>

</html>s