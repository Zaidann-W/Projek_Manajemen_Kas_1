<?php
include __DIR__ . '/../services/authservice.php';
include __DIR__ . '/../config/config.php';
requireLogin();

$userId   = getUserId();
$namaUser = getUserName();
$namaUmkm = "Bisnis Saya";

$stmtSaldo = $conn->prepare("SELECT COALESCE(SUM(saldo_awal),0) AS total FROM akun_tf WHERE user_id = ?");
$stmtSaldo->execute([$userId]);
$totalSaldo = $stmtSaldo->fetch(PDO::FETCH_ASSOC)['total'];

$stmtMasuk = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id = ? AND tipe = 'pemasukan' AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
$stmtMasuk->execute([$userId]);
$totalMasuk = $stmtMasuk->fetch(PDO::FETCH_ASSOC)['total'];

$stmtKeluar = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id = ? AND tipe = 'pengeluaran' AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
$stmtKeluar->execute([$userId]);
$totalKeluar = $stmtKeluar->fetch(PDO::FETCH_ASSOC)['total'];

$laba = $totalMasuk - $totalKeluar;

$stmtAkun = $conn->prepare("SELECT nama_akun, jenis_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY saldo_awal DESC");
$stmtAkun->execute([$userId]);
$akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);

$stmtTx = $conn->prepare("SELECT t.tanggal, t.tipe, t.jumlah, t.keterangan, a.nama_akun FROM transaksi t LEFT JOIN akun_tf a ON t.akuntf_id = a.id WHERE t.user_id = ? ORDER BY t.tanggal DESC, t.id DESC LIMIT 8");
$stmtTx->execute([$userId]);
$transaksiTerbaru = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
$jenisIcon = ['kas'=>'K','bank'=>'B','wallet'=>'W','kredit'=>'C'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard SmartKas</title>
</head>

<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main">
        <div class="topbar">
            <div class="welcome">
                <h1><?= $namaUmkm ?></h1>
                <p>Halo, <?= htmlspecialchars($namaUser) ?> â€” Selamat datang kembali!</p>
            </div>
            <div class="topbar-date"><?= date('d F Y') ?></div>
        </div>

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

        <div class="bottom-grid">
            <div class="table-card">
                <div class="table-card-header">
                    <h2>Aktivitas Terbaru</h2>
                    <a href="../laporan/harian.php">Lihat semua â†’</a>
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
                        <a href="../transaksi/pemasukan.php" class="q-btn green"><span>+</span>Pemasukan</a>
                        <a href="../transaksi/pengeluaran.php" class="q-btn red"><span>âˆ’</span>Pengeluaran</a>
                        <a href="../transaksi/transfer.php" class="q-btn purple"><span>â‡„</span>Transfer</a>
                        <a href="../laporan/harian.php" class="q-btn blue"><span>â‰¡</span>Laporan</a>
                    </div>
                </div>

                <div class="akun-card">
                    <div class="akun-card-header">
                        <h2>Akun Keuangan</h2>
                        <a href="../data/akuntf.php">Kelola â†’</a>
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
</body>

</html>