<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../config/config.php';


if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pw = $_POST['password'] ?? '';

    if(empty($email) || empty($pw)) {
        $_SESSION['error'] = 'Email dan password wajib diisi';
        header("Location: login.php");
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if($user && $pw === $user['password']) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['nama'];

            // $stmtUmkm = $conn->prepare("SELECT * FROM umkm WHERE user_id = ?");
            // $stmtUmkm->execute([$user['id']]);
            // $umkm = $stmtUmkm->fetch(PDO::FETCH_ASSOC);
            // $_SESSION['umkm'] = $umkm;

            header("Location: ../dashboard/index.php");
            exit;
        } else {
            if(!$user) {
                $_SESSION['error'] = "Email belum terdaftar, silahkan register terlebih dahulu";
            } else {
                $_SESSION['error'] = "Password salah";
            }
            header('Location: login.php');
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Terjadi kesalahan sistem';
        header('Location: login.php');
        exit;
    }
}



?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Login | Sistem Manajemen</title>

    <style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: 'Segoe UI', sans-serif;
        height: 100vh;
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        display: flex;
        justify-content: center;
        align-items: center;
        color: #fff;
    }

    .login-wrapper {
        width: 100%;
        max-width: 420px;
        padding: 20px;
    }

    .login-card {
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(14px);
        border-radius: 18px;
        padding: 35px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.6s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .login-card h1 {
        margin: 0;
        font-size: 26px;
        font-weight: 700;
        text-align: center;
    }

    .subtitle {
        text-align: center;
        font-size: 14px;
        color: #ccc;
        margin-top: 6px;
        margin-bottom: 28px;
    }

    .form-group {
        margin-bottom: 18px;
    }

    label {
        font-size: 14px;
        font-weight: 600;
        display: block;
        margin-bottom: 6px;
    }

    input {
        width: 100%;
        padding: 12px 14px;
        border-radius: 10px;
        border: none;
        outline: none;
        font-size: 14px;
    }

    input:focus {
        box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.6);
    }

    .btn-login {
        width: 100%;
        padding: 14px;
        margin-top: 10px;
        border-radius: 12px;
        border: none;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        background: linear-gradient(135deg, #d4af37, #f5d76e);
        color: #000;
        transition: 0.3s;
    }

    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(212, 175, 55, 0.6);
    }

    .footer-text {
        margin-top: 20px;
        text-align: center;
        font-size: 13px;
        color: #bbb;
    }

    .footer-text a {
        color: #f5d76e;
        text-decoration: none;
    }

    .footer-text a:hover {
        text-decoration: underline;
    }
    </style>
</head>

<body>

    <div class="login-wrapper">
        <div class="login-card">
            <h1>Selamat Datang</h1>
            <p class="subtitle">Silakan login untuk melanjutkan</p>
            <!-- Pesan muncul di sini -->
            <?php if(isset($_SESSION['error'])) : ?>
            <div class="alert"><?= $_SESSION['error']; ?></div>
            <?php unset($_SESSION['error']); endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Email</label>
                    <input type="text" name="email" placeholder="Masukkan email">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Masukkan password">
                </div>

                <button type="submit" class="btn-login">
                    Login
                </button>
            </form>

            <div class="footer-text">
                Belum punya akun?
                <a href="register.php">Daftar Sekarang</a>
            </div>
        </div>
    </div>

</body>

</html>