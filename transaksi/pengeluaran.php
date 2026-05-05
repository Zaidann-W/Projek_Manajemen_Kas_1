<?php
if (!defined('PARTIAL_MODE')) { header('Location: index.php?tab=pengeluaran'); exit; }

if (!function_exists('rp')) { function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); } }

$userId  = getUserId();
$ownerId = getOwnerUserId();
$error = '';
$success = '';

$stmtAkun = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY nama_akun");
$stmtAkun->execute([$ownerId]);
$akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);

$stmtKat = $conn->prepare("SELECT id, nama_kategori FROM kategori_cashflow WHERE user_id = ? AND kategori = 'keluar' ORDER BY nama_kategori");
$stmtKat->execute([$ownerId]);
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
            $stmtCek->execute([$rekening, $ownerId]);
            $akun = $stmtCek->fetch(PDO::FETCH_ASSOC);
            if (!$akun) throw new Exception('Akun tidak ditemukan');

            if ($akun['saldo_awal'] < $jumlah) {
                $error = "Saldo tidak mencukupi! Saldo akun: Rp " . number_format($akun['saldo_awal'], 0, ',', '.');
                $conn->rollBack();
            } else {
                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, akuntf_id, kategoricf_id, tipe, jumlah, keterangan, tanggal) VALUES (?, ?, ?, 'pengeluaran', ?, ?, ?)");
                $stmt->execute([$userId, $rekening, $kategoriId ?: null, $jumlah, $keterangan, $tanggal]);
                $conn->prepare("UPDATE akun_tf SET saldo_awal = saldo_awal - ? WHERE id = ? AND user_id = ?")->execute([$jumlah, $rekening, $ownerId]);
                $conn->commit();
                $success = "Pengeluaran berhasil disimpan!";

                $stmtAkun = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY nama_akun");
                $stmtAkun->execute([$ownerId]);
                $akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Terjadi kesalahan sistem";
        }
    }
}
?>
<div class="form-wrapper">
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-title">Detail Pengeluaran</div>
        <form method="POST" action="?tab=pengeluaran">
            <div class="form-group">
                <label>Pilih Rekening</label>
                <select name="rekening" required>
                    <option value="">-- Pilih Rekening --</option>
                    <?php foreach ($akunList as $akun): ?>
                    <option value="<?= $akun['id'] ?>"><?= htmlspecialchars($akun['nama_akun']) ?> (<?= rp($akun['saldo_awal']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Kategori <span style="color:var(--text-muted);font-weight:400">(Opsional)</span></label>
                <select name="kategori_id">
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($kategoriList as $kat): ?>
                    <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Jumlah (Rp)</label>
                <input type="number" name="jumlah" placeholder="Masukkan nominal" min="1" required>
            </div>
            <div class="form-group">
                <label>Tanggal</label>
                <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Keterangan</label>
                <textarea name="keterangan" placeholder="Contoh: Beli bahan baku, bayar listrik..." rows="3" required></textarea>
            </div>
            <button type="submit" class="btn-submit red">Simpan Pengeluaran</button>
        </form>
    </div>
</div>



<div class="saldo-info red-info">
    <strong>Saldo Rekening Saat Ini:</strong>
    <?php if (count($akunList) > 0): ?>
        <?php foreach ($akunList as $akun): ?>
            <span><?= htmlspecialchars($akun['nama_akun']) ?>: <?= rp($akun['saldo_awal']) ?></span>
        <?php endforeach; ?>
    <?php else: ?>
        <span>Belum ada rekening</span>
    <?php endif; ?>
</div>
