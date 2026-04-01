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
$error = '';
$success = '';

$stmtAkun = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY nama_akun");
$stmtAkun->execute([$userId]);
$akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);

$stmtKat = $conn->prepare("SELECT id, nama_kategori FROM kategori_cashflow WHERE user_id = ? AND kategori = 'keluar' ORDER BY nama_kategori");
$stmtKat->execute([$userId]);
$kategoriList = $stmtKat->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rekening   = trim($_POST['rekening'] ?? '');
    $jumlah     = $_POST['jumlah'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    $tanggal    = $_POST['tanggal'] ?? '';
    $kategoriId = $_POST['kategori_id'] ?? null;

    if (empty($rekening) || empty($jumlah) || empty($tanggal) || empty($keterangan)) {
        $error = "Semua field wajib diisi!";
    } elseif ($jumlah <= 0) {
        $error = "Jumlah harus lebih dari 0!";
    } else {
        try {
            $conn->beginTransaction();
            $stmtCek = $conn->prepare("SELECT id, saldo_awal FROM akun_tf WHERE id = ? AND user_id = ?");
            $stmtCek->execute([$rekening, $userId]);
            $akun = $stmtCek->fetch(PDO::FETCH_ASSOC);

            if (!$akun) throw new Exception('Akun tidak ditemukan');

            if ($akun['saldo_awal'] < $jumlah) {
                $error = "Saldo tidak mencukupi! Saldo akun: Rp " . number_format($akun['saldo_awal'], 0, ',', '.');
                $conn->rollBack();
            } else {
                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, akuntf_id, kategoricf_id, tipe, jumlah, keterangan, tanggal) VALUES (?, ?, ?, 'pengeluaran', ?, ?, ?)");
                $stmt->execute([$userId, $rekening, $kategoriId ?: null, $jumlah, $keterangan, $tanggal]);

                $stmtUpdate = $conn->prepare("UPDATE akun_tf SET saldo_awal = saldo_awal - ? WHERE id = ? AND user_id = ?");
                $stmtUpdate->execute([$jumlah, $rekening, $userId]);

                $conn->commit();
                $success = "Pengeluaran berhasil disimpan!";

                $stmtAkun = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY nama_akun");
                $stmtAkun->execute([$userId]);
                $akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Tambah Pengeluaran</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', sans-serif
    }

    body {
        background: #f1f5f9;
        display: flex;
        min-height: 100vh
    }

    .main {
        flex: 1;
        padding: 30px 36px
    }

    .page-header {
        margin-bottom: 28px
    }

    .page-header h1 {
        font-size: 22px;
        font-weight: 700;
        color: #0f172a
    }

    .page-header p {
        font-size: 14px;
        color: #64748b;
        margin-top: 4px
    }

    .form-wrapper {
        max-width: 620px
    }

    .card {
        background: #fff;
        padding: 28px;
        border-radius: 16px;
        box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
        margin-bottom: 20px
    }

    .card-title {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 1px solid #f1f5f9
    }

    .form-group {
        margin-bottom: 18px
    }

    label {
        font-size: 13px;
        font-weight: 600;
        color: #475569;
        display: block;
        margin-bottom: 7px
    }

    input,
    select,
    textarea {
        width: 100%;
        padding: 11px 14px;
        border-radius: 10px;
        border: 1.5px solid #e2e8f0;
        font-size: 14px;
        color: #1e293b;
        transition: .2s;
        background: #fff
    }

    input:focus,
    select:focus,
    textarea:focus {
        outline: none;
        border-color: #dc2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, .1)
    }

    textarea {
        resize: none;
        height: 85px
    }

    .saldo-info {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 8px;
        padding: 9px 14px;
        font-size: 13px;
        color: #991b1b;
        margin-top: 8px;
        display: none
    }

    .saldo-info.show {
        display: block
    }

    .btn-submit {
        width: 100%;
        padding: 13px;
        background: #dc2626;
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: .2s;
        margin-top: 4px
    }

    .btn-submit:hover {
        background: #b91c1c
    }

    .note {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 10px;
        text-align: center
    }

    .alert {
        padding: 13px 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px
    }

    .alert-error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca
    }

    .alert-success {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0
    }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main">
        <div class="page-header">
            <h1>💸 Tambah Pengeluaran</h1>
            <p>Catat uang keluar dari akun keuangan kamu</p>
        </div>
        <div class="form-wrapper">
            <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-title">Detail Pengeluaran</div>
                <form method="POST">
                    <div class="form-group">
                        <label>Pilih Rekening</label>
                        <select name="rekening" id="rekeningSelect" onchange="tampilSaldo(this)">
                            <option value="">-- Pilih Rekening --</option>
                            <?php foreach ($akunList as $akun): ?>
                            <option value="<?= $akun['id'] ?>" data-saldo="<?= $akun['saldo_awal'] ?>"
                                <?= (isset($_POST['rekening']) && $_POST['rekening'] == $akun['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($akun['nama_akun']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="saldo-info" id="saldoInfo"></div>
                    </div>

                    <div class="form-group">
                        <label>Kategori <span style="color:#94a3b8;font-weight:400">(Opsional)</span></label>
                        <select name="kategori_id">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategoriList as $kat): ?>
                            <option value="<?= $kat['id'] ?>"
                                <?= (isset($_POST['kategori_id']) && $_POST['kategori_id'] == $kat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kat['nama_kategori']) ?>
                            </option>
                            <?php endforeach; ?>
                            <?php if (count($kategoriList) === 0): ?>
                            <option disabled>Belum ada kategori — tambah di menu Kategori</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Jumlah (Rp)</label>
                        <input type="number" name="jumlah" placeholder="Masukkan nominal" min="1"
                            value="<?= htmlspecialchars($_POST['jumlah'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" name="tanggal"
                            value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="keterangan"
                            placeholder="Contoh: Beli bahan baku, bayar listrik..."><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">💸 Simpan Pengeluaran</button>
                    <div class="note">* Saldo akun akan otomatis berkurang</div>
                </form>
            </div>
        </div>
    </div>
    <script>
    function tampilSaldo(select) {
        const opt = select.options[select.selectedIndex];
        const saldo = opt.getAttribute('data-saldo');
        const box = document.getElementById('saldoInfo');
        if (saldo !== null && select.value !== '') {
            box.textContent = '💳 Saldo tersedia: Rp ' + parseInt(saldo).toLocaleString('id-ID');
            box.classList.add('show');
        } else {
            box.classList.remove('show');
        }
    }
    window.onload = function() {
        const sel = document.getElementById('rekeningSelect');
        if (sel.value) tampilSaldo(sel);
    }
    </script>
</body>

</html>