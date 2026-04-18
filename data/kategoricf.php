<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../services/authservice.php';
include __DIR__ . '/../config/config.php';
requireLogin();

$userId = getUserId();
$error  = '';
$editData = null;

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $conn->prepare("DELETE FROM kategori_cashflow WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
    $_SESSION['success'] = "Kategori berhasil dihapus";
    header("Location: kategoricf.php"); exit;
}

if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM kategori_cashflow WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $namaKategori = trim($_POST['nama_kategori'] ?? '');
    $kategori     = $_POST['kategori'] ?? '';

    if (empty($namaKategori) || empty($kategori)) {
        $error = "Semua field wajib diisi!";
    } else {
        if (isset($_POST['id']) && $_POST['id'] != '') {
            $conn->prepare("UPDATE kategori_cashflow SET nama_kategori = ?, kategori = ? WHERE id = ? AND user_id = ?")
                 ->execute([$namaKategori, $kategori, $_POST['id'], $userId]);
            $_SESSION['success'] = "Kategori berhasil diperbarui";
        } else {
            $conn->prepare("INSERT INTO kategori_cashflow (user_id, nama_kategori, kategori) VALUES (?, ?, ?)")
                 ->execute([$userId, $namaKategori, $kategori]);
            $_SESSION['success'] = "Kategori berhasil ditambahkan";
        }
        header("Location: kategoricf.php"); exit;
    }
}

$stmt = $conn->prepare("SELECT * FROM kategori_cashflow WHERE user_id = ? ORDER BY kategori, nama_kategori");
$stmt->execute([$userId]);
$kategoriList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Cashflow</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main">
        <h1>Kategori Cashflow</h1>

        <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); endif; ?>
        <?php if($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <div class="form-wrapper">
            <div class="card">
                <div class="card-title"><?= $editData ? 'Edit Kategori' : 'Tambah Kategori Baru' ?></div>
                <form method="POST">
                    <?php if($editData): ?>
                    <input type="hidden" name="id" value="<?= $editData['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Nama Kategori</label>
                        <input type="text" name="nama_kategori" placeholder="Contoh: Gaji, Belanja, Transport..."
                            value="<?= $editData ? htmlspecialchars($editData['nama_kategori']) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Tipe</label>
                        <div class="tipe-grid">
                            <button type="button"
                                class="tipe-btn masuk <?= (!$editData || $editData['kategori']==='masuk') ? 'active' : '' ?>"
                                onclick="setTipe('masuk')">Pemasukan</button>
                            <button type="button"
                                class="tipe-btn keluar <?= ($editData && $editData['kategori']==='keluar') ? 'active' : '' ?>"
                                onclick="setTipe('keluar')">Pengeluaran</button>
                        </div>
                        <input type="hidden" name="kategori" id="tipeInput"
                            value="<?= $editData ? $editData['kategori'] : 'masuk' ?>">
                    </div>

                    <button type="submit" class="btn-submit"><?= $editData ? 'Update Kategori' : 'Simpan Kategori' ?></button>
                    <?php if ($editData): ?>
                    <a href="kategoricf.php" class="cancel-link">Batal</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header"><h2>Daftar Kategori</h2><span class="result-count"><?= count($kategoriList) ?> kategori</span></div>
            <table>
                <thead><tr><th>#</th><th>Nama Kategori</th><th>Tipe</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php if (count($kategoriList) > 0): $no = 1; foreach ($kategoriList as $k): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td style="font-weight:600"><?= htmlspecialchars($k['nama_kategori']) ?></td>
                        <td><span class="badge <?= $k['kategori'] ?>"><?= $k['kategori'] === 'masuk' ? 'Pemasukan' : 'Pengeluaran' ?></span></td>
                        <td>
                            <a href="?edit=<?= $k['id'] ?>" class="action-btn btn-edit">Edit</a>
                            <a href="?delete=<?= $k['id'] ?>" onclick="return confirm('Yakin hapus kategori ini?')" class="action-btn btn-delete">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="4" class="empty">Belum ada kategori</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    function setTipe(val) {
        document.getElementById('tipeInput').value = val;
        document.querySelectorAll('.tipe-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.tipe-btn.' + val).classList.add('active');
    }
    </script>
</body>
</html>