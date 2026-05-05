<?php
if (!defined('PARTIAL_MODE')) { header('Location: index.php?tab=monitoring'); exit; }

$userId  = getOwnerUserId();
$bizIds  = getBusinessUserIds();
$biz     = buildInClause($bizIds);

$filterBulan = isset($_GET['fb']) ? (int) $_GET['fb'] : (int) date('m');
$filterTahun = isset($_GET['ft']) ? (int) $_GET['ft'] : (int) date('Y');

$stmtMonitor = $conn->prepare("
    SELECT b.id, b.jumlah_budget, k.nama_kategori, COALESCE(SUM(t.jumlah), 0) AS realisasi
    FROM budget b
    LEFT JOIN kategori_cashflow k ON b.kategoricf_id = k.id
    LEFT JOIN transaksi t ON t.kategoricf_id = b.kategoricf_id AND t.user_id IN ({$biz['placeholders']}) AND t.tipe = 'pengeluaran' AND MONTH(t.tanggal) = b.bulan AND YEAR(t.tanggal) = b.tahun
    WHERE b.user_id = ? AND b.bulan = ? AND b.tahun = ?
    GROUP BY b.id, b.jumlah_budget, k.nama_kategori ORDER BY k.nama_kategori
");
$stmtMonitor->execute(array_merge($biz['values'], [$userId, $filterBulan, $filterTahun]));
$monitorList = $stmtMonitor->fetchAll(PDO::FETCH_ASSOC);

$totalBudget = 0; $totalRealisasi = 0;
foreach ($monitorList as $m) { $totalBudget += $m['jumlah_budget']; $totalRealisasi += $m['realisasi']; }
$totalSisa = $totalBudget - $totalRealisasi;

if (!function_exists('rp')) { function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); } }
$namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>

<div class="filter-card">
    <form method="GET" class="filter-form">
        <input type="hidden" name="tab" value="monitoring">
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

<div class="summary-cards">
    <div class="summary-card"><div class="label">Total Budget</div><div class="value" style="color:var(--amber)"><?= rp($totalBudget) ?></div></div>
    <div class="summary-card red"><div class="label">Total Terpakai</div><div class="value"><?= rp($totalRealisasi) ?></div></div>
    <div class="summary-card <?= $totalSisa >= 0 ? 'green' : 'red' ?>"><div class="label">Sisa Budget</div><div class="value"><?= rp(abs($totalSisa)) ?></div></div>
</div>

<div class="table-card">
    <div class="table-header"><h2>Monitoring Budget — <?= $namaBulan[$filterBulan] ?> <?= $filterTahun ?></h2></div>
    <table>
        <thead><tr><th>#</th><th>Kategori</th><th>Budget</th><th>Terpakai</th><th>Sisa</th><th>Progress</th></tr></thead>
        <tbody>
            <?php if (count($monitorList) > 0): $no = 1; foreach ($monitorList as $m):
                $sisa = $m['jumlah_budget'] - $m['realisasi'];
                $persen = $m['jumlah_budget'] > 0 ? round(($m['realisasi'] / $m['jumlah_budget']) * 100) : 0;
                $barClass = $persen >= 100 ? 'red' : ($persen >= 75 ? 'amber' : 'green');
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($m['nama_kategori'] ?? '-') ?></td>
                <td style="font-weight:700;color:var(--amber)"><?= rp($m['jumlah_budget']) ?></td>
                <td style="font-weight:700;color:var(--red)"><?= rp($m['realisasi']) ?></td>
                <td class="<?= $sisa >= 0 ? 'green' : 'red' ?>" style="font-weight:700"><?= rp(abs($sisa)) ?></td>
                <td>
                    <div class="progress-bar"><div class="progress-fill <?= $barClass ?>" style="width:<?= min($persen, 100) ?>%"></div></div>
                    <span style="font-size:12px;color:var(--text-muted)"><?= $persen ?>%</span>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" class="empty">Belum ada budget untuk bulan ini. Tambah di tab Kelola Budget.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
