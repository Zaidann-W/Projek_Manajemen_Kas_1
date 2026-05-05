<?php
if (!defined('PARTIAL_MODE')) { header('Location: index.php?tab=transfer'); exit; }

if (!function_exists('rp')) { function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); } }

$userId  = getUserId();
$ownerId = getOwnerUserId();
$error   = '';
$success = '';

$stmtAkun = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY nama_akun");
$stmtAkun->execute([$ownerId]);
$akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dariAkun   = trim($_POST['dari_akun'] ?? '');
    $keAkun     = trim($_POST['ke_akun'] ?? '');
    $jumlah     = $_POST['jumlah'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    $tanggal    = $_POST['tanggal'] ?? '';

    if (empty($dariAkun) || empty($keAkun) || empty($jumlah) || empty($tanggal)) {
        $error = "Semua field wajib diisi!";
    } elseif ($dariAkun == $keAkun) {
        $error = "Akun asal dan tujuan tidak boleh sama!";
    } elseif ($jumlah <= 0) {
        $error = "Jumlah harus lebih dari 0!";
    } else {
        try {
            $conn->beginTransaction();
            $stmtCek = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE id = ? AND user_id = ?");
            $stmtCek->execute([$dariAkun, $ownerId]);
            $akunAsal = $stmtCek->fetch(PDO::FETCH_ASSOC);
            if (!$akunAsal) throw new Exception('Akun asal tidak ditemukan');

            if ($akunAsal['saldo_awal'] < $jumlah) {
                $error = "Saldo tidak mencukupi! Saldo " . htmlspecialchars($akunAsal['nama_akun']) . ": Rp " . number_format($akunAsal['saldo_awal'], 0, ',', '.');
                $conn->rollBack();
            } else {
                $stmtTujuan = $conn->prepare("SELECT id FROM akun_tf WHERE id = ? AND user_id = ?");
                $stmtTujuan->execute([$keAkun, $ownerId]);
                if (!$stmtTujuan->fetch()) throw new Exception('Akun tujuan tidak ditemukan');

                $conn->prepare("INSERT INTO transfer (user_id, dari_akuntf, ke_akuntf, jumlah, tanggal, keterangan) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$userId, $dariAkun, $keAkun, $jumlah, $tanggal, $keterangan]);
                $conn->prepare("INSERT INTO transaksi (user_id, akuntf_id, tipe, jumlah, keterangan, tanggal) VALUES (?, ?, 'transfer', ?, ?, ?)")
                    ->execute([$userId, $dariAkun, $jumlah, $keterangan, $tanggal]);
                $conn->prepare("UPDATE akun_tf SET saldo_awal = saldo_awal - ? WHERE id = ? AND user_id = ?")->execute([$jumlah, $dariAkun, $ownerId]);
                $conn->prepare("UPDATE akun_tf SET saldo_awal = saldo_awal + ? WHERE id = ? AND user_id = ?")->execute([$jumlah, $keAkun, $ownerId]);
                $conn->commit();
                $success = "Transfer berhasil disimpan!";

                $stmtAkun = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY nama_akun");
                $stmtAkun->execute([$ownerId]);
                $akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}
?>
<div class="form-wrapper">
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-title">Transfer Antar Rekening</div>
        <form method="POST" action="?tab=transfer">
            <div class="form-group">
                <label>Dari Rekening</label>
                <select name="dari_akun" required>
                    <option value="">-- Pilih Rekening Asal --</option>
                    <?php foreach ($akunList as $akun): ?>
                    <option value="<?= $akun['id'] ?>"><?= htmlspecialchars($akun['nama_akun']) ?> (<?= rp($akun['saldo_awal']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Ke Rekening</label>
                <select name="ke_akun" required>
                    <option value="">-- Pilih Rekening Tujuan --</option>
                    <?php foreach ($akunList as $akun): ?>
                    <option value="<?= $akun['id'] ?>"><?= htmlspecialchars($akun['nama_akun']) ?> (<?= rp($akun['saldo_awal']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Jumlah (Rp)</label>
                <input type="number" name="jumlah" placeholder="Masukkan nominal transfer" min="1" required>
            </div>
            <div class="form-group">
                <label>Tanggal</label>
                <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Keterangan <span style="color:var(--text-muted);font-weight:400">(Opsional)</span></label>
                <textarea name="keterangan" placeholder="Contoh: Tarik tunai dari bank, isi saldo GoPay..." rows="3"></textarea>
            </div>
            <button type="submit" class="btn-submit purple">Proses Transfer</button>
        </form>
    </div>
</div>


