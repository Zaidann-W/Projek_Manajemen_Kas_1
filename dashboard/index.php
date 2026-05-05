<?php
include __DIR__ . '/../services/authservice.php';
include __DIR__ . '/../config/config.php';
requireLogin();

$userId   = getUserId();
$ownerId  = getOwnerUserId();
$bizIds   = getBusinessUserIds();
$biz      = buildInClause($bizIds);
$namaUser = getUserName();
$namaUmkm = "Bisnis Saya";

$stmtSaldo = $conn->prepare("SELECT COALESCE(SUM(saldo_awal),0) AS total FROM akun_tf WHERE user_id = ?");
$stmtSaldo->execute([$ownerId]);
$totalSaldo = $stmtSaldo->fetch(PDO::FETCH_ASSOC)['total'];

$stmtMasuk = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id IN ({$biz['placeholders']}) AND tipe = 'pemasukan' AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
$stmtMasuk->execute($biz['values']);
$totalMasuk = $stmtMasuk->fetch(PDO::FETCH_ASSOC)['total'];

$stmtKeluar = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id IN ({$biz['placeholders']}) AND tipe = 'pengeluaran' AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
$stmtKeluar->execute($biz['values']);
$totalKeluar = $stmtKeluar->fetch(PDO::FETCH_ASSOC)['total'];

$laba = $totalMasuk - $totalKeluar;

$stmtAkun = $conn->prepare("SELECT nama_akun, jenis_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY saldo_awal DESC");
$stmtAkun->execute([$ownerId]);
$akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);

$stmtTx = $conn->prepare("SELECT t.tanggal, t.tipe, t.jumlah, t.keterangan, a.nama_akun FROM transaksi t LEFT JOIN akun_tf a ON t.akuntf_id = a.id WHERE t.user_id IN ({$biz['placeholders']}) ORDER BY t.tanggal DESC, t.id DESC LIMIT 8");
$stmtTx->execute($biz['values']);
$transaksiTerbaru = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
$jenisIcon = ['kas'=>'K','bank'=>'B','wallet'=>'W','kredit'=>'C'];

// === CHART DATA: 7 hari terakhir ===
$chartLabels = [];
$chartMasuk  = [];
$chartKeluar = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('d M', strtotime($d));

    $sm = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS t FROM transaksi WHERE user_id IN ({$biz['placeholders']}) AND tipe='pemasukan' AND tanggal=?");
    $sm->execute(array_merge($biz['values'], [$d]));
    $chartMasuk[] = (float)$sm->fetch()['t'];

    $sk = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS t FROM transaksi WHERE user_id IN ({$biz['placeholders']}) AND tipe='pengeluaran' AND tanggal=?");
    $sk->execute(array_merge($biz['values'], [$d]));
    $chartKeluar[] = (float)$sk->fetch()['t'];
}

// === CHART DATA: Pengeluaran per kategori bulan ini ===
$stmtKatChart = $conn->prepare("
    SELECT k.nama_kategori, SUM(t.jumlah) AS total
    FROM transaksi t
    LEFT JOIN kategori_cashflow k ON t.kategoricf_id = k.id
    WHERE t.user_id IN ({$biz['placeholders']}) AND t.tipe = 'pengeluaran'
    AND MONTH(t.tanggal) = MONTH(CURDATE()) AND YEAR(t.tanggal) = YEAR(CURDATE())
    GROUP BY k.nama_kategori ORDER BY total DESC LIMIT 6
");
$stmtKatChart->execute($biz['values']);
$katData = $stmtKatChart->fetchAll(PDO::FETCH_ASSOC);
$katLabels = []; $katValues = [];
foreach ($katData as $kd) {
    $katLabels[] = $kd['nama_kategori'] ?? 'Tanpa Kategori';
    $katValues[] = (float)$kd['total'];
}

// === BUDGET ALERTS: Cek budget bulan ini ===
$budgetAlerts = [];
$stmtBudget = $conn->prepare("
    SELECT b.jumlah_budget, k.nama_kategori, COALESCE(SUM(t.jumlah), 0) AS realisasi
    FROM budget b
    LEFT JOIN kategori_cashflow k ON b.kategoricf_id = k.id
    LEFT JOIN transaksi t ON t.kategoricf_id = b.kategoricf_id
        AND t.user_id IN ({$biz['placeholders']})
        AND t.tipe = 'pengeluaran'
        AND MONTH(t.tanggal) = MONTH(CURDATE())
        AND YEAR(t.tanggal) = YEAR(CURDATE())
    WHERE b.user_id = ? AND b.bulan = MONTH(CURDATE()) AND b.tahun = YEAR(CURDATE())
    GROUP BY b.id, b.jumlah_budget, k.nama_kategori
");
$stmtBudget->execute(array_merge($biz['values'], [$ownerId]));
$budgetRows = $stmtBudget->fetchAll(PDO::FETCH_ASSOC);
foreach ($budgetRows as $br) {
    if ($br['jumlah_budget'] > 0) {
        $persen = ($br['realisasi'] / $br['jumlah_budget']) * 100;
        if ($persen >= 80) {
            $budgetAlerts[] = [
                'kategori' => $br['nama_kategori'] ?? 'Tanpa Kategori',
                'budget'   => $br['jumlah_budget'],
                'terpakai' => $br['realisasi'],
                'persen'   => round($persen, 1),
                'level'    => $persen >= 100 ? 'danger' : 'warning'
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard SmartKas</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>

<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main">
        <div class="topbar">
            <div class="welcome">
                <h1><?= $namaUmkm ?></h1>
                <p>Halo, <?= htmlspecialchars($namaUser) ?> - Selamat datang kembali!</p>
            </div>
            <div class="topbar-date"><?= date('d F Y') ?></div>
        </div>

        <?php if (count($budgetAlerts) > 0): ?>
        <div class="budget-alerts">
            <?php foreach ($budgetAlerts as $alert): ?>
            <div class="budget-alert <?= $alert['level'] ?>">
                <div class="budget-alert-icon">
                    <?= $alert['level'] === 'danger' ? '!' : '!' ?>
                </div>
                <div class="budget-alert-content">
                    <div class="budget-alert-title">
                        <?php if ($alert['level'] === 'danger'): ?>
                            Budget <strong><?= htmlspecialchars($alert['kategori']) ?></strong> sudah terlampaui!
                        <?php else: ?>
                            Budget <strong><?= htmlspecialchars($alert['kategori']) ?></strong> hampir habis
                        <?php endif; ?>
                    </div>
                    <div class="budget-alert-detail">
                        Terpakai <?= rp($alert['terpakai']) ?> dari <?= rp($alert['budget']) ?>
                    </div>
                </div>
                <div class="budget-alert-badge <?= $alert['level'] ?>">
                    <?= $alert['persen'] ?>%
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="cards">
            <div class="card c-blue">
                <div class="card-icon">S</div>
                <h3>Total Saldo</h3>
                <div class="value"><?= rp($totalSaldo) ?></div>
            </div>
            <div class="card c-green">
                <div class="card-icon">M</div>
                <h3>Pemasukan Bulan Ini</h3>
                <div class="value"><?= rp($totalMasuk) ?></div>
            </div>
            <div class="card c-red">
                <div class="card-icon">K</div>
                <h3>Pengeluaran Bulan Ini</h3>
                <div class="value"><?= rp($totalKeluar) ?></div>
            </div>
            <div class="card <?= $laba >= 0 ? 'c-orange' : 'c-red' ?>">
                <div class="card-icon">L</div>
                <h3>Laba Bulan Ini</h3>
                <div class="value"><?= rp(abs($laba)) ?></div>
            </div>
        </div>

        <!-- CHARTS -->
        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h2>Tren 7 Hari Terakhir</h2>
                    <div class="chart-legend">
                        <span class="legend-dot green"></span> Pemasukan
                        <span class="legend-dot red"></span> Pengeluaran
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <div class="chart-card chart-card-small">
                <div class="chart-card-header">
                    <h2>Pengeluaran per Kategori</h2>
                </div>
                <div class="chart-wrapper chart-wrapper-donut">
                    <?php if (count($katData) > 0): ?>
                    <canvas id="categoryChart"></canvas>
                    <?php else: ?>
                    <div class="empty" style="padding:40px 0">Belum ada data pengeluaran bulan ini</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bottom-grid">
            <div class="table-card">
                <div class="table-card-header">
                    <h2>Aktivitas Terbaru</h2>
                    <a href="../laporan/index.php">Lihat semua &rarr;</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th>Akun</th>
                            <th>Tipe</th>
                            <th>Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($transaksiTerbaru) > 0): foreach ($transaksiTerbaru as $tx): ?>
                        <tr>
                            <td><?= date('d M', strtotime($tx['tanggal'])) ?></td>
                            <td><?= $tx['keterangan'] ? htmlspecialchars($tx['keterangan']) : '<span style="color:var(--text-muted)">-</span>' ?>
                            </td>
                            <td><?= htmlspecialchars($tx['nama_akun'] ?? '-') ?></td>
                            <td><span class="badge <?= $tx['tipe'] ?>"><?= $tx['tipe'] ?></span></td>
                            <td class="<?= $tx['tipe']==='pemasukan'?'green':($tx['tipe']==='pengeluaran'?'red':'') ?>" style="font-weight:700">
                                <?= ($tx['tipe']==='pemasukan'?'+':($tx['tipe']==='pengeluaran'?'-':'')) . rp($tx['jumlah']) ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="5" class="empty">Belum ada transaksi</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="right-panel">
                <div class="quick-card">
                    <h2>Quick Action</h2>
                    <div class="quick-btns">
                        <a href="../transaksi/index.php?tab=pemasukan" class="q-btn green"><span>+</span>Pemasukan</a>
                        <a href="../transaksi/index.php?tab=pengeluaran" class="q-btn red"><span>&minus;</span>Pengeluaran</a>
                        <a href="../transaksi/index.php?tab=transfer" class="q-btn purple"><span>&hArr;</span>Transfer</a>
                        <a href="../laporan/index.php" class="q-btn blue"><span>&equiv;</span>Laporan</a>
                    </div>
                </div>

                <div class="akun-card">
                    <div class="akun-card-header">
                        <h2>Akun Keuangan</h2>
                        <a href="../data/index.php?tab=akun">Kelola &rarr;</a>
                    </div>
                    <div class="akun-list">
                        <?php if (count($akunList) > 0): foreach ($akunList as $akun):
                        $icon = $jenisIcon[$akun['jenis_akun']] ?? 'A';
                    ?>
                        <div class="akun-item">
                            <div class="akun-left">
                                <div class="akun-emoji"><?= $icon ?></div>
                                <div>
                                    <div class="akun-nama"><?= htmlspecialchars($akun['nama_akun']) ?></div>
                                    <div class="akun-jenis"><?= $akun['jenis_akun'] ?></div>
                                </div>
                            </div>
                            <div class="akun-saldo"><?= rp($akun['saldo_awal']) ?></div>
                        </div>
                        <?php endforeach; else: ?>
                        <div class="empty">Belum ada akun</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
(function() {
    // Theme-aware colors
    var isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    var gridColor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)';
    var textColor = isDark ? '#8896b0' : '#6b6188';

    // === TREND CHART (Line) ===
    var trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [
                    {
                        label: 'Pemasukan',
                        data: <?= json_encode($chartMasuk) ?>,
                        borderColor: '#34d399',
                        backgroundColor: 'rgba(52,211,153,.1)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#34d399',
                        pointBorderColor: isDark ? '#0f1f3a' : '#faf8ff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Pengeluaran',
                        data: <?= json_encode($chartKeluar) ?>,
                        borderColor: '#f87171',
                        backgroundColor: 'rgba(248,113,113,.1)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#f87171',
                        pointBorderColor: isDark ? '#0f1f3a' : '#faf8ff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? '#1a2d50' : '#faf8ff',
                        titleColor: isDark ? '#f0f4fb' : '#1a1230',
                        bodyColor: isDark ? '#e2e8f4' : '#2d2545',
                        borderColor: isDark ? '#1a2d50' : '#e0dced',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(ctx) {
                                return ctx.dataset.label + ': Rp ' + ctx.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: gridColor, drawBorder: false },
                        ticks: { color: textColor, font: { size: 12 } }
                    },
                    y: {
                        grid: { color: gridColor, drawBorder: false },
                        ticks: {
                            color: textColor,
                            font: { size: 12 },
                            callback: function(v) {
                                if (v >= 1000000) return 'Rp ' + (v/1000000).toFixed(1) + 'jt';
                                if (v >= 1000) return 'Rp ' + (v/1000).toFixed(0) + 'rb';
                                return 'Rp ' + v;
                            }
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // === CATEGORY CHART (Doughnut) ===
    var catCtx = document.getElementById('categoryChart');
    if (catCtx) {
        var catColors = ['#a78bfa','#5b9cf6','#34d399','#fbbf24','#f87171','#f472b6'];
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($katLabels) ?>,
                datasets: [{
                    data: <?= json_encode($katValues) ?>,
                    backgroundColor: catColors.slice(0, <?= count($katLabels) ?>),
                    borderColor: isDark ? '#0f1f3a' : '#faf8ff',
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColor,
                            padding: 14,
                            font: { size: 12 },
                            usePointStyle: true,
                            pointStyleWidth: 10
                        }
                    },
                    tooltip: {
                        backgroundColor: isDark ? '#1a2d50' : '#faf8ff',
                        titleColor: isDark ? '#f0f4fb' : '#1a1230',
                        bodyColor: isDark ? '#e2e8f4' : '#2d2545',
                        borderColor: isDark ? '#1a2d50' : '#e0dced',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(ctx) {
                                return ctx.label + ': Rp ' + ctx.parsed.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    }
})();
</script>
</body>

</html>
