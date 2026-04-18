<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../services/authservice.php';
include __DIR__ . '/../config/config.php';
requireLogin();

$userId = getUserId();
$tahun  = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$stmtMasuk = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id = ? AND tipe = 'pemasukan' AND YEAR(tanggal) = ?");
$stmtMasuk->execute([$userId, $tahun]);
$totalMasuk = $stmtMasuk->fetch(PDO::FETCH_ASSOC)['total'];

$stmtKeluar = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id = ? AND tipe = 'pengeluaran' AND YEAR(tanggal) = ?");
$stmtKeluar->execute([$userId, $tahun]);
$totalKeluar = $stmtKeluar->fetch(PDO::FETCH_ASSOC)['total'];

$saldo = $totalMasuk - $totalKeluar;

$stmtPerBulan = $conn->prepare("
    SELECT MONTH(tanggal) AS bulan,
        SUM(CASE WHEN tipe='pemasukan' THEN jumlah ELSE 0 END) AS masuk,
        SUM(CASE WHEN tipe='pengeluaran' THEN jumlah ELSE 0 END) AS keluar
    FROM transaksi WHERE user_id = ? AND YEAR(tanggal) = ?
    GROUP BY MONTH(tanggal) ORDER BY MONTH(tanggal)
");
$stmtPerBulan->execute([$userId, $tahun]);
$perBulan = $stmtPerBulan->fetchAll(PDO::FETCH_ASSOC);

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
$namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Tahunan</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main">
        <h1>Laporan Tahunan</h1>

        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Tahun</label>
                    <select name="tahun">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == $tahun ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn-filter">Lihat</button>
            </form>
        </div>

        <div class="cards">
            <div class="card c-green"><h3>Total Pemasukan</h3><div class="value green"><?= rp($totalMasuk) ?></div></div>
            <div class="card c-red"><h3>Total Pengeluaran</h3><div class="value red"><?= rp($totalKeluar) ?></div></div>
            <div class="card <?= $saldo >= 0 ? 'c-blue' : 'c-red' ?>"><h3>Saldo Tahun <?= $tahun ?></h3><div class="value <?= $saldo >= 0 ? 'blue' : 'red' ?>"><?= rp(abs($saldo)) ?></div></div>
        </div>

        <div class="table-card" style="padding:20px">
            <div class="section-title" style="margin-bottom:14px">Ringkasan Per Bulan â€” Tahun <?= $tahun ?></div>
            <table>
                <thead><tr><th>Bulan</th><th>Pemasukan</th><th>Pengeluaran</th><th>Selisih</th></tr></thead>
                <tbody>
                    <?php if (count($perBulan) > 0): foreach ($perBulan as $row):
                        $sel = $row['masuk'] - $row['keluar']; ?>
                    <tr>
                        <td><?= $namaBulan[(int)$row['bulan']] ?></td>
                        <td class="green" style="font-weight:600"><?= rp($row['masuk']) ?></td>
                        <td class="red" style="font-weight:600"><?= rp($row['keluar']) ?></td>
                        <td class="<?= $sel >= 0 ? 'blue' : 'red' ?>" style="font-weight:700"><?= ($sel >= 0 ? '+' : '-') . rp(abs($sel)) ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="4" class="empty">Tidak ada transaksi tahun ini</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>