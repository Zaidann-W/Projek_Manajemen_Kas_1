<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../services/authservice.php';
include __DIR__ . '/../config/config.php';
requireLogin();

$userId = getUserId();
$error  = '';

$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'update_profil') {
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($nama) || empty($email)) {
        $error = "Nama dan email wajib diisi!";
    } else {
        $cek = $conn->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
        $cek->execute([$email, $userId]);
        if ($cek->fetch()) {
            $error = "Email sudah digunakan akun lain!";
        } else {
            $conn->prepare("UPDATE user SET nama = ?, email = ? WHERE user_id = ?")
                 ->execute([$nama, $email, $userId]);
            $_SESSION['user_name'] = $nama;
            $_SESSION['success'] = "Profil berhasil diperbarui!";
            header("Location: user.php"); exit;
        }
    }
}

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
        header("Location: user.php"); exit;
    }
}

$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main">
        <h1>Pengaturan Akun</h1>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-wrapper">
            <!-- PROFIL -->
            <div class="card">
                <div class="user-info">
                    <div class="avatar"><?= strtoupper(substr($user['nama'], 0, 1)) ?></div>
                    <div class="user-detail">
                        <h3><?= htmlspecialchars($user['nama']) ?></h3>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                        <span class="badge-join">Bergabung <?= date('d M Y', strtotime($user['created_at'])) ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">Edit Profil</div>
                <form method="POST">
                    <input type="hidden" name="aksi" value="update_profil">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama" value="<?= htmlspecialchars($user['nama']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>">
                    </div>
                    <button type="submit" class="btn-submit">Simpan Perubahan</button>
                </form>
            </div>

            <div class="card">
                <div class="card-title">Ganti Password</div>
                <form method="POST">
                    <input type="hidden" name="aksi" value="ganti_password">
                    <div class="form-group">
                        <label>Password Lama</label>
                        <input type="password" name="pw_lama" placeholder="Masukkan password lama">
                    </div>
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="pw_baru" placeholder="Masukkan password baru">
                    </div>
                    <div class="form-group">
                        <label>Konfirmasi Password</label>
                        <input type="password" name="pw_konfirmasi" placeholder="Ulangi password baru">
                    </div>
                    <button type="submit" class="btn-submit">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>