<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../services/authservice.php';
include __DIR__ . '/../config/config.php';
requireLogin();

$userId = getUserId();
$today = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

$stmtMasuk = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id = ? AND tipe = 'pemasukan' AND tanggal = ?");
$stmtMasuk->execute([$userId, $today]);
$totalMasuk = $stmtMasuk->fetch(PDO::FETCH_ASSOC)['total'];

$stmtKeluar = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id = ? AND tipe = 'pengeluaran' AND tanggal = ?");
$stmtKeluar->execute([$userId, $today]);
$totalKeluar = $stmtKeluar->fetch(PDO::FETCH_ASSOC)['total'];

$saldo = $totalMasuk - $totalKeluar;

$stmtDetail = $conn->prepare("
    SELECT t.*, a.nama_akun FROM transaksi t 
    LEFT JOIN akun_tf a ON t.akuntf_id = a.id 
    WHERE t.user_id = ? AND t.tanggal = ? ORDER BY t.id DESC
");
$stmtDetail->execute([$userId, $today]);
$detail = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Harian</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main">
        <h1>Laporan Harian</h1>

        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Tanggal</label>
                    <input type="date" name="tanggal" value="<?= $today ?>">
                </div>
                <button type="submit" class="btn-filter">Lihat</button>
            </form>
        </div>

        <div class="cards">
            <div class="card c-green"><h3>Total Pemasukan</h3><div class="value green"><?= rp($totalMasuk) ?></div></div>
            <div class="card c-red"><h3>Total Pengeluaran</h3><div class="value red"><?= rp($totalKeluar) ?></div></div>
            <div class="card <?= $saldo >= 0 ? 'c-blue' : 'c-red' ?>"><h3>Saldo Hari Ini</h3><div class="value <?= $saldo >= 0 ? 'blue' : 'red' ?>"><?= rp(abs($saldo)) ?></div></div>
        </div>

        <div class="table-card" style="padding:20px">
            <div class="section-title" style="margin-bottom:14px">Detail Transaksi â€” <?= date('d F Y', strtotime($today)) ?></div>
            <table>
                <thead><tr><th>#</th><th>Keterangan</th><th>Akun</th><th>Tipe</th><th>Jumlah</th></tr></thead>
                <tbody>
                    <?php if (count($detail) > 0): $no = 1; foreach ($detail as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['keterangan']) ?></td>
                        <td><?= htmlspecialchars($row['nama_akun'] ?? '-') ?></td>
                        <td><span class="badge <?= $row['tipe'] ?>"><?= $row['tipe'] ?></span></td>
                        <td class="<?= $row['tipe']==='pemasukan'?'green':($row['tipe']==='pengeluaran'?'red':'') ?>" style="font-weight:600">
                            <?= ($row['tipe']==='pemasukan' ? '+' : ($row['tipe']==='pengeluaran' ? '-' : '')) . rp($row['jumlah']) ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" class="empty">Tidak ada transaksi hari ini</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>