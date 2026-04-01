<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$error  = '';

// Ambil data user
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// UPDATE PROFIL
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'update_profil') {
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($nama) || empty($email)) {
        $error = "Nama dan email wajib diisi!";
    } else {
        // Cek email udah dipake user lain belum
        $cek = $conn->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
        $cek->execute([$email, $userId]);
        if ($cek->fetch()) {
            $error = "Email sudah digunakan akun lain!";
        } else {
            $conn->prepare("UPDATE user SET nama = ?, email = ? WHERE user_id = ?")
                 ->execute([$nama, $email, $userId]);
            $_SESSION['user_name'] = $nama;
            $_SESSION['success'] = "Profil berhasil diperbarui!";
            header("Location: user.php");
            exit;
        }
    }
}

// GANTI PASSWORD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'ganti_password') {
    $pwLama = $_POST['pw_lama'] ?? '';
    $pwBaru = $_POST['pw_baru'] ?? '';
    $pwKonfirmasi = $_POST['pw_konfirmasi'] ?? '';

    if (empty($pwLama) || empty($pwBaru) || empty($pwKonfirmasi)) {
        $error = "Semua field password wajib diisi!";
    } elseif ($pwLama !== $user['password']) {
        $error = "Password lama salah!";
    } elseif ($pwBaru !== $pwKonfirmasi) {
        $error = "Konfirmasi password tidak cocok!";
    } else {
        $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?")
             ->execute([$pwBaru, $userId]);
        $_SESSION['success'] = "Password berhasil diperbarui!";
        header("Location: user.php");
        exit;
    }
}

// Refresh data user
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Profil User</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: Segoe UI
    }

    body {
        background: #f4f6fb;
        display: flex
    }

    .sidebar {
        width: 220px;
        background: #111827;
        color: #fff;
        min-height: 100vh;
        padding: 25px;
    }

    .logo {
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 40px;
    }

    .sidebar a {
        display: block;
        padding: 10px;
        color: #cbd5e1;
        text-decoration: none;
        margin-bottom: 8px;
        border-radius: 8px;
    }

    .sidebar a:hover {
        background: #1f2937;
        color: #fff
    }

    .active {
        background: #2563eb !important;
        color: #fff !important
    }

    .main {
        flex: 1;
        padding: 30px;
        max-width: 800px;
    }

    h1 {
        margin-bottom: 20px;
    }

    .card {
        background: #fff;
        padding: 24px;
        border-radius: 14px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .05);
        margin-bottom: 24px;
    }

    .card h2 {
        margin-bottom: 16px;
        font-size: 16px;
        border-bottom: 1px solid #f3f4f6;
        padding-bottom: 12px;
    }

    label {
        font-size: 14px;
        font-weight: 600;
        color: #444;
        display: block;
        margin-bottom: 6px;
    }

    input {
        width: 100%;
        padding: 10px 12px;
        margin-bottom: 14px;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 14px;
    }

    input:focus {
        outline: none;
        border-color: #2563eb;
    }

    input[readonly] {
        background: #f9fafb;
        color: #6b7280;
    }

    button {
        background: #2563eb;
        color: #fff;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
    }

    button:hover {
        opacity: .9
    }

    .success {
        background: #dcfce7;
        color: #166534;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 14px;
        font-size: 14px;
    }

    .error {
        background: #fee2e2;
        color: #991b1b;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 14px;
        font-size: 14px;
    }

    .avatar {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: #fff;
        font-weight: 700;
        margin-bottom: 16px;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 18px;
        margin-bottom: 8px;
    }

    .user-detail h3 {
        font-size: 18px;
        font-weight: 700;
    }

    .user-detail p {
        color: #6b7280;
        font-size: 14px;
    }

    .badge-join {
        display: inline-block;
        background: #ede9fe;
        color: #7c3aed;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        margin-top: 6px;
    }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main">
        <h1>👤 Profil Saya</h1>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="success">✅ <?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="error">⚠️ <?= $error ?></div>
        <?php endif; ?>

        <!-- INFO USER -->
        <div class="card">
            <div class="user-info">
                <div class="avatar"><?= strtoupper(substr($user['nama'], 0, 1)) ?></div>
                <div class="user-detail">
                    <h3><?= htmlspecialchars($user['nama']) ?></h3>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                    <span class="badge-join">🗓 Bergabung <?= date('d F Y', strtotime($user['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- EDIT PROFIL -->
        <div class="card">
            <h2>✏️ Edit Profil</h2>
            <form method="POST">
                <input type="hidden" name="aksi" value="update_profil">
                <label>Nama Lengkap</label>
                <input type="text" name="nama" value="<?= htmlspecialchars($user['nama']) ?>" required>
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                <button type="submit">Simpan Perubahan</button>
            </form>
        </div>

        <!-- GANTI PASSWORD -->
        <div class="card">
            <h2>🔒 Ganti Password</h2>
            <form method="POST">
                <input type="hidden" name="aksi" value="ganti_password">
                <label>Password Lama</label>
                <input type="password" name="pw_lama" placeholder="Masukkan password lama">
                <label>Password Baru</label>
                <input type="password" name="pw_baru" placeholder="Masukkan password baru">
                <label>Konfirmasi Password Baru</label>
                <input type="password" name="pw_konfirmasi" placeholder="Ulangi password baru">
                <button type="submit" style="background:#dc2626;">Ganti Password</button>
            </form>
        </div>
    </div>
</body>

</html>