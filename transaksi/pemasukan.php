<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../services/authservice.php';
include __DIR__ . '/../config/config.php';
requireLogin();

$userId = getUserId();
$error = '';
$success = '';

$stmtAkun = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY nama_akun");
$stmtAkun->execute([$userId]);
$akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);

$stmtKat = $conn->prepare("SELECT id, nama_kategori FROM kategori_cashflow WHERE user_id = ? AND kategori = 'masuk' ORDER BY nama_kategori");
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
            $stmtSaldo = $conn->prepare("SELECT id FROM akun_tf WHERE id = ? AND user_id = ?");
            $stmtSaldo->execute([$rekening, $userId]);
            $cariSaldo = $stmtSaldo->fetch(PDO::FETCH_ASSOC);
            if (!$cariSaldo) throw new Exception('Akun tidak ditemukan');

            $stmt = $conn->prepare("INSERT INTO transaksi (user_id, akuntf_id, kategoricf_id, tipe, jumlah, keterangan, tanggal) VALUES (?, ?, ?, 'pemasukan', ?, ?, ?)");
            $stmt->execute([$userId, $rekening, $kategoriId ?: null, $jumlah, $keterangan, $tanggal]);

            $stmtUpdate = $conn->prepare("UPDATE akun_tf SET saldo_awal = saldo_awal + ? WHERE id = ? AND user_id = ?");
            $stmtUpdate->execute([$jumlah, $rekening, $userId]);

            $conn->commit();
            $success = "Pemasukan berhasil disimpan!";

            $stmtAkun = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY nama_akun");
            $stmtAkun->execute([$userId]);
            $akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Terjadi kesalahan sistem";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pemasukan</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main">
        <div class="page-header">
            <h1>Tambah Pemasukan</h1>
            <p>Catat uang masuk ke akun keuangan kamu</p>
        </div>
        <div class="form-wrapper">
            <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-title">Detail Pemasukan</div>
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
                        <div class="saldo-info green-info" id="saldoInfo"></div>
                    </div>

                    <div class="form-group">
                        <label>Kategori <span style="color:var(--text-muted);font-weight:400">(Opsional)</span></label>
                        <select name="kategori_id">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategoriList as $kat): ?>
                            <option value="<?= $kat['id'] ?>"
                                <?= (isset($_POST['kategori_id']) && $_POST['kategori_id'] == $kat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kat['nama_kategori']) ?>
                            </option>
                            <?php endforeach; ?>
                            <?php if (count($kategoriList) === 0): ?>
                            <option disabled>Belum ada kategori â€” tambah di menu Kategori</option>
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
                            placeholder="Contoh: Penjualan produk A, transfer dari klien..."><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit green">Simpan Pemasukan</button>
                    <div class="note">* Saldo akun akan otomatis bertambah</div>
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
            box.textContent = 'Saldo saat ini: Rp ' + parseInt(saldo).toLocaleString('id-ID');
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