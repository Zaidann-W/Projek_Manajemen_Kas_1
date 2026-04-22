<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../services/authservice.php';
include __DIR__ . '/../config/config.php';
requireLogin();

$userId = getUserId();

$filterBulan = isset($_GET['fb']) ? (int) $_GET['fb'] : (int) date('m');
$filterTahun = isset($_GET['ft']) ? (int) $_GET['ft'] : (int) date('Y');

$stmtMonitor = $conn->prepare("
    SELECT b.id, b.jumlah_budget, k.nama_kategori, COALESCE(SUM(t.jumlah), 0) AS realisasi
    FROM budget b
    LEFT JOIN kategori_cashflow k ON b.kategoricf_id = k.id
    LEFT JOIN transaksi t ON t.kategoricf_id = b.kategoricf_id AND t.user_id = b.user_id AND t.tipe = 'pengeluaran' AND MONTH(t.tanggal) = b.bulan AND YEAR(t.tanggal) = b.tahun
    WHERE b.user_id = ? AND b.bulan = ? AND b.tahun = ?
    GROUP BY b.id, b.jumlah_budget, k.nama_kategori ORDER BY k.nama_kategori
");
$stmtMonitor->execute([$userId, $filterBulan, $filterTahun]);
$monitorList = $stmtMonitor->fetchAll(PDO::FETCH_ASSOC);

$totalBudget = 0; $totalRealisasi = 0;
foreach ($monitorList as $m) { $totalBudget += $m['jumlah_budget']; $totalRealisasi += $m['realisasi']; }

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
$namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Budget</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main">
        <div class="page-header">
            <h1>Monitoring Budget</h1>
            <p>Pantau realisasi pengeluaran vs budget yang telah direncanakan</p>
        </div>

        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Bulan</label>
                    <select name="fb">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $filterBulan == $i ? 'selected' : '' ?>><?= $namaBulan[$i] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Tahun</label>
                    <select name="ft">
                        <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                        <option value="<?= $y ?>" <?= $filterTahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn-filter">Tampilkan</button>
            </form>
        </div>

        <?php if (count($monitorList) > 0): ?>
        <div class="summary-cards">
            <div class="summary-card amber"><h3>Total Budget</h3><div class="value"><?= rp($totalBudget) ?></div></div>
            <div class="summary-card red"><h3>Total Terpakai</h3><div class="value"><?= rp($totalRealisasi) ?></div></div>
            <div class="summary-card blue"><h3>Sisa Budget</h3><div class="value"><?= rp($totalBudget - $totalRealisasi) ?></div></div>
        </div>
        <?php endif; ?>

        <div class="table-card">
            <div class="table-header">
                <h2>Realisasi — <?= $namaBulan[$filterBulan] ?> <?= $filterTahun ?></h2>
            </div>
            <table>
                <thead><tr><th>Kategori</th><th>Budget</th><th>Terpakai</th><th>Sisa</th><th>Progress</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (count($monitorList) > 0): foreach ($monitorList as $m):
                        $budget = $m['jumlah_budget']; $real = $m['realisasi']; $sisa = $budget - $real;
                        $persen = $budget > 0 ? round(($real / $budget) * 100) : 0;
                        $barWidth = min($persen, 100);
                        if ($persen <= 75) { $barClass = 'green'; $statusClass = 'aman'; $statusText = 'Aman'; }
                        elseif ($persen <= 100) { $barClass = 'yellow'; $statusClass = 'waspada'; $statusText = 'Hampir habis'; }
                        else { $barClass = 'red'; $statusClass = 'bahaya'; $statusText = 'Over budget!'; }
                    ?>
                    <tr>
                        <td style="font-weight:600"><?= htmlspecialchars($m['nama_kategori'] ?? '-') ?></td>
                        <td style="font-weight:600;color:var(--amber)"><?= rp($budget) ?></td>
                        <td style="font-weight:600;color:<?= $persen > 100 ? 'var(--red)' : 'var(--text-primary)' ?>"><?= rp($real) ?></td>
                        <td style="font-weight:600" class="<?= $sisa < 0 ? 'red' : 'green' ?>"><?= $sisa < 0 ? '-' : '' ?><?= rp(abs($sisa)) ?></td>
                        <td>
                            <div class="progress-wrapper">
                                <div class="progress-bar"><div class="progress-fill <?= $barClass ?>" style="width:<?= $barWidth ?>%"></div></div>
                                <div class="progress-text"><?= $persen ?>% terpakai</div>
                            </div>
                        </td>
                        <td><span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6" class="empty">
                            Belum ada budget untuk bulan ini.<br>
                            <a href="index.php" style="color:var(--amber);font-weight:600">Tambah budget &rarr;</a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
