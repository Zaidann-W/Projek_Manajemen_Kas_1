<?php
if (!defined('PARTIAL_MODE')) { header('Location: index.php?tab=bulanan'); exit; }

$userId = getUserId();
$bizIds = getBusinessUserIds();
$biz    = buildInClause($bizIds);
$bulan  = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$parts  = explode('-', $bulan);
$tahun  = $parts[0];
$bln    = $parts[1];

$stmtMasuk = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id IN ({$biz['placeholders']}) AND tipe = 'pemasukan' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmtMasuk->execute(array_merge($biz['values'], [$bln, $tahun]));
$totalMasuk = $stmtMasuk->fetch(PDO::FETCH_ASSOC)['total'];

$stmtKeluar = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id IN ({$biz['placeholders']}) AND tipe = 'pengeluaran' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmtKeluar->execute(array_merge($biz['values'], [$bln, $tahun]));
$totalKeluar = $stmtKeluar->fetch(PDO::FETCH_ASSOC)['total'];

$saldo = $totalMasuk - $totalKeluar;

$stmtDetail = $conn->prepare("SELECT t.*, a.nama_akun FROM transaksi t LEFT JOIN akun_tf a ON t.akuntf_id = a.id WHERE t.user_id IN ({$biz['placeholders']}) AND MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ? ORDER BY t.tanggal DESC, t.id DESC");
$stmtDetail->execute(array_merge($biz['values'], [$bln, $tahun]));
$detail = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('rp')) { function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); } }
$namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>

<div class="filter-card">
    <form method="GET" class="filter-form">
        <input type="hidden" name="tab" value="bulanan">
        <div class="filter-group">
            <label>Pilih Bulan</label>
            <input type="month" name="bulan" value="<?= $bulan ?>">
        </div>
        <button type="submit" class="btn-filter">Tampilkan</button>
    </form>
</div>

<div class="summary-cards">
    <div class="summary-card green"><div class="label">Pemasukan</div><div class="value"><?= rp($totalMasuk) ?></div></div>
    <div class="summary-card red"><div class="label">Pengeluaran</div><div class="value"><?= rp($totalKeluar) ?></div></div>
    <div class="summary-card <?= $saldo >= 0 ? 'green' : 'red' ?>"><div class="label">Saldo</div><div class="value"><?= rp(abs($saldo)) ?></div></div>
</div>

<div class="table-card">
    <div class="table-header"><h2>Detail — <?= $namaBulan[(int)$bln] ?> <?= $tahun ?></h2></div>
    <table>
        <thead><tr><th>#</th><th>Tanggal</th><th>Tipe</th><th>Akun</th><th>Keterangan</th><th>Nominal</th></tr></thead>
        <tbody>
            <?php if (count($detail) > 0): $no = 1; foreach ($detail as $d): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= date('d M', strtotime($d['tanggal'])) ?></td>
                <td><span class="badge <?= $d['tipe'] ?>"><?= $d['tipe'] ?></span></td>
                <td><?= htmlspecialchars($d['nama_akun'] ?? '-') ?></td>
                <td><?= htmlspecialchars($d['keterangan'] ?? '-') ?></td>
                <td class="<?= $d['tipe']==='pemasukan'?'green':($d['tipe']==='pengeluaran'?'red':'') ?>" style="font-weight:700">
                    <?= ($d['tipe']==='pemasukan'?'+':($d['tipe']==='pengeluaran'?'-':'')) . rp($d['jumlah']) ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" class="empty">Tidak ada transaksi bulan ini</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
