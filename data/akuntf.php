<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("location: ../auth/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$error = '';
$editData = null;

// delete data
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM akun_tf WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);

    $_SESSION['success'] = "Akun berhasil dihapus";
    header("Location: akuntf.php");
    exit;
}

/* ================= LOAD EDIT ================= */
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM akun_tf WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $editData = $stmt->fetch();
}

// insert dan update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $namaAkun  = trim($_POST['akun'] ?? '');
    $jenisAkun = $_POST['jenis'] ?? '';
    $saldoAwal = $_POST['saldo_awal'];

    if (empty($namaAkun) || empty($jenisAkun)) {
        $error = "SILAHKAN ISI TERLEBIH DAHULU";
    } else {

        if (isset($_POST['id']) && $_POST['id'] != '') {
            // UPDATE
            $stmt = $conn->prepare("
                UPDATE akun_tf 
                SET nama_akun=?, jenis_akun=?, saldo_awal=? 
                WHERE id=? AND user_id=?
            ");
            $stmt->execute([
                $namaAkun,
                $jenisAkun,
                $saldoAwal,
                $_POST['id'],
                $userId
            ]);

            $_SESSION['success'] = "Akun berhasil diperbarui";
        } else {
            // INSERT
            $stmt = $conn->prepare("
                INSERT INTO akun_tf (user_id, nama_akun, jenis_akun, saldo_awal) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $namaAkun,
                $jenisAkun,
                $saldoAwal
            ]);

            $_SESSION['success'] = "Akun berhasil ditambahkan";
        }

        header("Location: akuntf.php");
        exit;
    }
}

// tampilkan data yang di insert
$show = $conn->prepare("SELECT * FROM akun_tf WHERE user_id = ? ORDER BY id DESC");
$show->execute([$userId]);
$akunList = $show->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Master Akun</title>

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

    .main {
        flex: 1;
        padding: 30px
    }

    h1 {
        margin-bottom: 20px
    }

    .card {
        background: #fff;
        padding: 20px;
        border-radius: 14px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, .05);
        margin-bottom: 25px;
    }

    input,
    select {
        width: 100%;
        padding: 10px;
        margin-top: 6px;
        margin-bottom: 12px;
        border-radius: 8px;
        border: 1px solid #ccc;
    }

    button {
        background: #2563eb;
        color: #fff;
        padding: 10px 16px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }

    button:hover {
        opacity: .9
    }

    .success {
        background: #dcfce7;
        color: #166534;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    .error {
        background: #fee2e2;
        color: #991b1b;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px
    }

    th,
    td {
        padding: 12px;
        border-bottom: 1px solid #eee
    }

    th {
        text-align: left;
        color: #666
    }

    .action-btn {
        padding: 6px 10px;
        border-radius: 6px;
        text-decoration: none;
        color: #fff;
        font-size: 12px;
    }

    .edit {
        background: #16a34a
    }

    .delete {
        background: #dc2626
    }
    </style>
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="main">
        <h1>Akun Keuangan</h1>

        <div class="card">
            <h2><?= $editData ? 'Edit Akun' : 'Tambah Akun Baru' ?></h2>

            <?php if(isset($_SESSION['success'])): ?>
            <div class="success"><?= $_SESSION['success'] ?></div>
            <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if($error): ?>
            <div class="error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <?php if($editData): ?>
                <input type="hidden" name="id" value="<?= $editData['id'] ?>">
                <?php endif; ?>

                <label>Nama Rekening</label>
                <input type="text" name="akun" value="<?= $editData ? htmlspecialchars($editData['nama_akun']) : '' ?>"
                    placeholder="Contoh: BCA, Kas Toko">

                <label>Jenis Rekening</label>
                <select name="jenis">
                    <option value="">-- Pilih Tipe --</option>
                    <?php
                $jenisList = ['kas','bank','wallet','kredit'];
                foreach($jenisList as $j):
                    $selected = ($editData && $editData['jenis_akun'] == $j) ? 'selected' : '';
                ?>
                    <option value="<?= $j ?>" <?= $selected ?>>
                        <?= ucfirst($j) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <label>Saldo Awal</label>
                <input type="number" name="saldo_awal" value="<?= $editData ? $editData['saldo_awal'] : 0 ?>">

                <button type="submit">
                    <?= $editData ? 'Update Akun' : 'Simpan Akun' ?>
                </button>
            </form>
        </div>

        <div class="card">
            <h2>Daftar Akun</h2>
            <table>
                <tr>
                    <th>Nama Akun</th>
                    <th>Tipe</th>
                    <th>Saldo</th>
                    <th>Aksi</th>
                </tr>

                <?php foreach($akunList as $akun): ?>
                <tr>
                    <td><?= htmlspecialchars($akun['nama_akun']) ?></td>
                    <td><?= strtoupper($akun['jenis_akun']) ?></td>
                    <td>Rp <?= number_format($akun['saldo_awal'],0,',','.') ?></td>
                    <td>
                        <a href="?edit=<?= $akun['id'] ?>" class="action-btn edit">Edit</a>
                        <a href="?delete=<?= $akun['id'] ?>" onclick="return confirm('Yakin hapus akun ini?')"
                            class="action-btn delete">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

    </div>

</body>

</html>