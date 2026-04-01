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
$editData = null;

// DELETE
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $conn->prepare("DELETE FROM kategori_cashflow WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
    $_SESSION['success'] = "Kategori berhasil dihapus";
    header("Location: kategoricf.php");
    exit;
}

// LOAD EDIT
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM kategori_cashflow WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// INSERT / UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $namaKategori = trim($_POST['nama_kategori'] ?? '');
    $kategori     = $_POST['kategori'] ?? '';

    if (empty($namaKategori) || empty($kategori)) {
        $error = "Semua field wajib diisi!";
    } else {
        if (isset($_POST['id']) && $_POST['id'] != '') {
            // UPDATE
            $conn->prepare("UPDATE kategori_cashflow SET nama_kategori = ?, kategori = ? WHERE id = ? AND user_id = ?")
                 ->execute([$namaKategori, $kategori, $_POST['id'], $userId]);
            $_SESSION['success'] = "Kategori berhasil diperbarui";
        } else {
            // INSERT
            $conn->prepare("INSERT INTO kategori_cashflow (user_id, nama_kategori, kategori) VALUES (?, ?, ?)")
                 ->execute([$userId, $namaKategori, $kategori]);
            $_SESSION['success'] = "Kategori berhasil ditambahkan";
        }
        header("Location: kategoricf.php");
        exit;
    }
}

// AMBIL SEMUA KATEGORI
$stmt = $conn->prepare("SELECT * FROM kategori_cashflow WHERE user_id = ? ORDER BY kategori, nama_kategori");
$stmt->execute([$userId]);
$kategoriList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kategori Cashflow</title>
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
    }

    label {
        font-size: 14px;
        font-weight: 600;
        color: #444;
        display: block;
        margin-bottom: 6px;
    }

    input,
    select {
        width: 100%;
        padding: 10px 12px;
        margin-bottom: 14px;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 14px;
    }

    input:focus,
    select:focus {
        outline: none;
        border-color: #2563eb;
    }

    button {
        background: #2563eb;
        color: #fff;
        padding: 10px 18px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
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

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
    }

    th {
        padding: 10px 14px;
        text-align: left;
        font-size: 12px;
        color: #9ca3af;
        text-transform: uppercase;
        border-bottom: 2px solid #f3f4f6;
    }

    td {
        padding: 12px 14px;
        border-bottom: 1px solid #f9fafb;
        font-size: 14px;
    }

    tr:hover td {
        background: #fafafa
    }

    .badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge.masuk {
        background: #dcfce7;
        color: #16a34a
    }

    .badge.keluar {
        background: #fee2e2;
        color: #dc2626
    }

    .action-btn {
        padding: 5px 12px;
        border-radius: 6px;
        text-decoration: none;
        color: #fff;
        font-size: 12px;
        margin-right: 4px;
    }

    .edit {
        background: #16a34a
    }

    .delete {
        background: #dc2626
    }

    .empty {
        text-align: center;
        color: #9ca3af;
        padding: 24px;
        font-size: 14px;
    }

    .tipe-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 14px;
    }

    .tipe-btn {
        padding: 10px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        cursor: pointer;
        text-align: center;
        font-size: 14px;
        font-weight: 500;
        background: transparent;
        color: #6b7280;
        transition: .2s;
    }

    .tipe-btn.masuk:hover,
    .tipe-btn.masuk.active {
        border-color: #16a34a;
        background: rgba(22, 163, 74, .1);
        color: #16a34a;
    }

    .tipe-btn.keluar:hover,
    .tipe-btn.keluar.active {
        border-color: #dc2626;
        background: rgba(220, 38, 38, .1);
        color: #dc2626;
    }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main">
        <h1> Kategori Cashflow</h1>

        <div class="card">
            <h2><?= $editData ? 'Edit Kategori' : 'Tambah Kategori Baru' ?></h2>

            <?php if (isset($_SESSION['success'])): ?>
            <div class="success">✅ <?= $_SESSION['success'] ?></div>
            <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="error">⚠️ <?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <?php if ($editData): ?>
                <input type="hidden" name="id" value="<?= $editData['id'] ?>">
                <?php endif; ?>

                <label>Nama Kategori</label>
                <input type="text" name="nama_kategori"
                    value="<?= $editData ? htmlspecialchars($editData['nama_kategori']) : '' ?>"
                    placeholder="Contoh: Gaji, Belanja, Transport...">

                <label>Tipe</label>
                <div class="tipe-grid">
                    <button type="button"
                        class="tipe-btn masuk <?= (!$editData || $editData['kategori']==='masuk') ? 'active' : '' ?>"
                        onclick="setTipe('masuk')">📈 Pemasukan</button>
                    <button type="button"
                        class="tipe-btn keluar <?= ($editData && $editData['kategori']==='keluar') ? 'active' : '' ?>"
                        onclick="setTipe('keluar')">📉 Pengeluaran</button>
                </div>
                <input type="hidden" name="kategori" id="tipeInput"
                    value="<?= $editData ? $editData['kategori'] : 'masuk' ?>">

                <button type="submit"><?= $editData ? 'Update Kategori' : 'Simpan Kategori' ?></button>
                <?php if ($editData): ?>
                <a href="kategoricf.php" style="margin-left:10px;color:#6b7280;font-size:14px;">Batal</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h2>Daftar Kategori</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Kategori</th>
                        <th>Tipe</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($kategoriList) > 0): $no = 1; foreach ($kategoriList as $kat): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($kat['nama_kategori']) ?></td>
                        <td><span
                                class="badge <?= $kat['kategori'] ?>"><?= $kat['kategori'] === 'masuk' ? '📈 Pemasukan' : '📉 Pengeluaran' ?></span>
                        </td>
                        <td>
                            <a href="?edit=<?= $kat['id'] ?>" class="action-btn edit">Edit</a>
                            <a href="?delete=<?= $kat['id'] ?>" onclick="return confirm('Yakin hapus kategori ini?')"
                                class="action-btn delete">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="4" class="empty">📭 Belum ada kategori</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    function setTipe(tipe) {
        document.getElementById('tipeInput').value = tipe;
        document.querySelectorAll('.tipe-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.tipe-btn.' + tipe).classList.add('active');
    }
    </script>
</body>

</html>