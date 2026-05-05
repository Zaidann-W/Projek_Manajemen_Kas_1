<?php
if (!defined('PARTIAL_MODE')) { header('Location: index.php?tab=tahunan'); exit; }

$userId = getUserId();
$bizIds = getBusinessUserIds();
$biz    = buildInClause($bizIds);
$tahun  = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$stmtMasuk = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id IN ({$biz['placeholders']}) AND tipe = 'pemasukan' AND YEAR(tanggal) = ?");
$stmtMasuk->execute(array_merge($biz['values'], [$tahun]));
$totalMasuk = $stmtMasuk->fetch(PDO::FETCH_ASSOC)['total'];

$stmtKeluar = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id IN ({$biz['placeholders']}) AND tipe = 'pengeluaran' AND YEAR(tanggal) = ?");
$stmtKeluar->execute(array_merge($biz['values'], [$tahun]));
$totalKeluar = $stmtKeluar->fetch(PDO::FETCH_ASSOC)['total'];

$saldo = $totalMasuk - $totalKeluar;

$stmtPerBulan = $conn->prepare("SELECT MONTH(tanggal) AS bulan, SUM(CASE WHEN tipe='pemasukan' THEN jumlah ELSE 0 END) AS masuk, SUM(CASE WHEN tipe='pengeluaran' THEN jumlah ELSE 0 END) AS keluar FROM transaksi WHERE user_id IN ({$biz['placeholders']}) AND YEAR(tanggal) = ? GROUP BY MONTH(tanggal) ORDER BY MONTH(tanggal)");
$stmtPerBulan->execute(array_merge($biz['values'], [$tahun]));
$perBulan = $stmtPerBulan->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('rp')) { function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); } }
$namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>

<div class="filter-card">
    <form method="GET" class="filter-form">
        <input type="hidden" name="tab" value="tahunan">
        <div class="filter-group">
            <label>Pilih Tahun</label>
            <select name="tahun">
                <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="btn-filter">Tampilkan</button>
    </form>
</div>

<div class="summary-cards">
    <div class="summary-card green"><div class="label">Total Pemasukan</div><div class="value"><?= rp($totalMasuk) ?></div></div>
    <div class="summary-card red"><div class="label">Total Pengeluaran</div><div class="value"><?= rp($totalKeluar) ?></div></div>
    <div class="summary-card <?= $saldo >= 0 ? 'green' : 'red' ?>"><div class="label">Laba/Rugi</div><div class="value"><?= rp(abs($saldo)) ?></div></div>
</div>

<div class="table-card">
    <div class="table-header"><h2>Rekap Per Bulan — <?= $tahun ?></h2></div>
    <table>
        <thead><tr><th>Bulan</th><th>Pemasukan</th><th>Pengeluaran</th><th>Saldo</th></tr></thead>
        <tbody>
            <?php if (count($perBulan) > 0): foreach ($perBulan as $pb): $s = $pb['masuk'] - $pb['keluar']; ?>
            <tr>
                <td style="font-weight:600"><?= $namaBulan[(int)$pb['bulan']] ?></td>
                <td class="green" style="font-weight:700">+<?= rp($pb['masuk']) ?></td>
                <td class="red" style="font-weight:700">-<?= rp($pb['keluar']) ?></td>
                <td class="<?= $s >= 0 ? 'green' : 'red' ?>" style="font-weight:700"><?= rp(abs($s)) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" class="empty">Belum ada data tahun ini</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
