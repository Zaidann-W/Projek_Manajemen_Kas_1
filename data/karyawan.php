<?php
if (!defined('PARTIAL_MODE')) { header('Location: index.php?tab=karyawan'); exit; }

$userId = getUserId();
$error  = '';

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id === $userId) {
        $_SESSION['error_k'] = "Tidak bisa menghapus akun sendiri!";
    } else {
        $conn->prepare("DELETE FROM user WHERE user_id = ? AND role = 'karyawan'")->execute([$id]);
        $_SESSION['success'] = "Karyawan berhasil dihapus";
    }
    header("Location: index.php?tab=karyawan"); exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pw    = $_POST['password'] ?? '';

    if (empty($nama) || empty($email) || empty($pw)) {
        $error = "Semua field wajib diisi!";
    } else {
        $cek = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
        $cek->execute([$email]);
        if ($cek->fetch()) {
            $error = "Email sudah digunakan!";
        } else {
            $conn->prepare("INSERT INTO user (nama, email, password, role, admin_id) VALUES (?, ?, ?, 'karyawan', ?)")
                 ->execute([$nama, $email, $pw, getUserId()]);
            $_SESSION['success'] = "Karyawan berhasil ditambahkan";
            header("Location: index.php?tab=karyawan"); exit;
        }
    }
}

$stmtAll = $conn->prepare("SELECT user_id, nama, email, role, created_at FROM user ORDER BY role ASC, nama ASC");
$stmtAll->execute();
$userList = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success"><?= $_SESSION['success'] ?></div>
<?php unset($_SESSION['success']); endif; ?>
<?php if (isset($_SESSION['error_k'])): ?>
<div class="alert alert-error"><?= $_SESSION['error_k'] ?></div>
<?php unset($_SESSION['error_k']); endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="form-wrapper">
    <div class="card">
        <div class="card-title">Tambah Karyawan Baru</div>
        <form method="POST" action="?tab=karyawan">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama" placeholder="Masukkan nama karyawan" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Masukkan email karyawan" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="text" name="password" placeholder="Buat password untuk karyawan" required>
            </div>
            <p class="note" style="margin-bottom:10px;text-align:left;color:var(--text-muted);font-size:12px">
                Karyawan hanya bisa mengakses: Dashboard, Transaksi, Monitoring Budget, dan Pengaturan profil sendiri.
            </p>
            <button type="submit" class="btn-submit">Tambah Karyawan</button>
        </form>
    </div>
</div>

<div class="table-card">
    <div class="table-header"><h2>Daftar Semua User</h2><span class="result-count"><?= count($userList) ?> user</span></div>
    <table>
        <thead><tr><th>#</th><th>Nama</th><th>Email</th><th>Role</th><th>Bergabung</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php if (count($userList) > 0): $no = 1; foreach ($userList as $u): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($u['nama']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge <?= $u['role'] === 'admin' ? 'pemasukan' : 'transfer' ?>"><?= ucfirst($u['role']) ?></span></td>
                <td style="color:var(--text-muted);font-size:13px"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <?php if ($u['role'] === 'karyawan'): ?>
                    <a href="?tab=karyawan&delete=<?= $u['user_id'] ?>" onclick="return confirm('Yakin hapus karyawan <?= htmlspecialchars($u['nama']) ?>?')" class="action-btn btn-delete">Hapus</a>
                    <?php else: ?>
                    <span style="color:var(--text-muted);font-size:12px">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" class="empty">Belum ada user</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
