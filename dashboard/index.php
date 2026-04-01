<?php
session_start();
include '../config/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
$userId   = $_SESSION['user_id'];
$namaUser = $_SESSION['user_name'] ?? 'User';
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
$jenisIcon = ['kas'=>'💵','bank'=>'🏦','wallet'=>'👛','kredit'=>'💳'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dashboard SmartKas</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', sans-serif
    }

    body {
        background: #f1f5f9;
        display: flex;
        min-height: 100vh
    }

    .main {
        flex: 1;
        padding: 28px 32px;
        overflow-x: hidden
    }

    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 28px;
        background: #fff;
        border-radius: 16px;
        padding: 18px 24px;
        box-shadow: 0 1px 6px rgba(0, 0, 0, .06)
    }

    .welcome {
        text-align: center;
        flex: 1;
    }

    .welcome h1 {
        font-size: 22px;
        font-weight: 700;
        color: #0f172a
    }

    .welcome p {
        font-size: 14px;
        color: #64748b;
        margin-top: 3px
    }

    .topbar-date {
        background: #f1f5f9;
        padding: 8px 14px;
        border-radius: 10px;
        font-size: 13px;
        color: #64748b;
        white-space: nowrap;
    }

    .cards {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 24px
    }

    .card {
        background: #fff;
        padding: 22px;
        border-radius: 16px;
        box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
        position: relative;
        overflow: hidden
    }

    .card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        border-radius: 16px 16px 0 0
    }

    .card.c-blue::before {
        background: #2563eb
    }

    .card.c-green::before {
        background: #16a34a
    }

    .card.c-red::before {
        background: #dc2626
    }

    .card.c-orange::before {
        background: #ea580c
    }

    .card-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-bottom: 14px
    }

    .card.c-blue .card-icon {
        background: #eff6ff
    }

    .card.c-green .card-icon {
        background: #f0fdf4
    }

    .card.c-red .card-icon {
        background: #fef2f2
    }

    .card.c-orange .card-icon {
        background: #fff7ed
    }

    .card h3 {
        font-size: 12px;
        color: #94a3b8;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .5px;
        margin-bottom: 6px
    }

    .card.c-blue .value {
        color: #2563eb
    }

    .card.c-green .value {
        color: #16a34a
    }

    .card.c-red .value {
        color: #dc2626
    }

    .card.c-orange .value {
        color: #ea580c
    }

    .value {
        font-size: 20px;
        font-weight: 700
    }

    .bottom-grid {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 20px
    }

    .table-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
        overflow: hidden
    }

    .table-card-header {
        padding: 18px 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f1f5f9
    }

    .table-card-header h2 {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a
    }

    .table-card-header a {
        font-size: 13px;
        color: #2563eb;
        text-decoration: none
    }

    table {
        width: 100%;
        border-collapse: collapse
    }

    th {
        padding: 11px 22px;
        text-align: left;
        font-size: 11px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: .5px;
        border-bottom: 1px solid #f8fafc
    }

    td {
        padding: 13px 22px;
        font-size: 14px;
        border-bottom: 1px solid #f8fafc;
        color: #334155
    }

    tr:last-child td {
        border: none
    }

    tr:hover td {
        background: #fafbff
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600
    }

    .badge.pemasukan {
        background: #f0fdf4;
        color: #16a34a
    }

    .badge.pengeluaran {
        background: #fef2f2;
        color: #dc2626
    }

    .badge.transfer {
        background: #f5f3ff;
        color: #7c3aed
    }

    .right-panel {
        display: flex;
        flex-direction: column;
        gap: 20px
    }

    .quick-card {
        background: #fff;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 1px 6px rgba(0, 0, 0, .06)
    }

    .quick-card h2 {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 14px
    }

    .quick-btns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px
    }

    .q-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 14px 10px;
        border-radius: 12px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        gap: 6px;
        transition: .2s
    }

    .q-btn:hover {
        opacity: .88;
        transform: translateY(-2px)
    }

    .q-btn span {
        font-size: 22px
    }

    .q-btn.green {
        background: #f0fdf4;
        color: #16a34a
    }

    .q-btn.red {
        background: #fef2f2;
        color: #dc2626
    }

    .q-btn.purple {
        background: #f5f3ff;
        color: #7c3aed
    }

    .q-btn.blue {
        background: #eff6ff;
        color: #2563eb
    }

    .akun-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
        overflow: hidden
    }

    .akun-card-header {
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f1f5f9
    }

    .akun-card-header h2 {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a
    }

    .akun-card-header a {
        font-size: 13px;
        color: #2563eb;
        text-decoration: none
    }

    .akun-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 11px 20px;
        border-bottom: 1px solid #f8fafc
    }

    .akun-item:last-child {
        border: none
    }

    .akun-left {
        display: flex;
        align-items: center;
        gap: 10px
    }

    .akun-emoji {
        font-size: 18px
    }

    .akun-nama {
        font-size: 14px;
        font-weight: 600;
        color: #1e293b
    }

    .akun-jenis {
        font-size: 11px;
        color: #94a3b8;
        text-transform: uppercase
    }

    .akun-saldo {
        font-size: 14px;
        font-weight: 700;
        color: #0f172a
    }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main">
        <div class="topbar">
            <div class="welcome">
                <h1><?= $namaUmkm ?></h1>
                <p>Halo, <?= htmlspecialchars($namaUser) ?> 👋 Selamat datang kembali!</p>
            </div>
            <div class="topbar-date">📅 <?= date('d F Y') ?></div>
        </div>

        <div class="cards">
            <div class="card c-blue">
                <div class="card-icon">💰</div>
                <h3>Total Saldo</h3>
                <div class="value"><?= rp($totalSaldo) ?></div>
            </div>
            <div class="card c-green">
                <div class="card-icon">📈</div>
                <h3>Pemasukan Bulan Ini</h3>
                <div class="value"><?= rp($totalMasuk) ?></div>
            </div>
            <div class="card c-red">
                <div class="card-icon">📉</div>
                <h3>Pengeluaran Bulan Ini</h3>
                <div class="value"><?= rp($totalKeluar) ?></div>
            </div>
            <div class="card <?= $laba >= 0 ? 'c-orange' : 'c-red' ?>">
                <div class="card-icon"><?= $laba >= 0 ? '✅' : '⚠️' ?></div>
                <h3>Laba Bulan Ini</h3>
                <div class="value"><?= rp(abs($laba)) ?></div>
            </div>
        </div>

        <div class="bottom-grid">
            <div class="table-card">
                <div class="table-card-header">
                    <h2>Aktivitas Terbaru</h2>
                    <a href="../laporan/harian.php">Lihat semua →</a>
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
                            <td><?= $tx['keterangan'] ? htmlspecialchars($tx['keterangan']) : '<span style="color:#cbd5e1">-</span>' ?>
                            </td>
                            <td><?= htmlspecialchars($tx['nama_akun'] ?? '-') ?></td>
                            <td><span class="badge <?= $tx['tipe'] ?>"><?= $tx['tipe'] ?></span></td>
                            <td
                                style="font-weight:700;color:<?= $tx['tipe']==='pemasukan'?'#16a34a':($tx['tipe']==='pengeluaran'?'#dc2626':'#7c3aed') ?>">
                                <?= ($tx['tipe']==='pemasukan'?'+':($tx['tipe']==='pengeluaran'?'-':'')) . rp($tx['jumlah']) ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center;color:#94a3b8;padding:32px">📭 Belum ada transaksi
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="right-panel">
                <div class="quick-card">
                    <h2>Quick Action</h2>
                    <div class="quick-btns">
                        <a href="../transaksi/pemasukan.php" class="q-btn green"><span>💹</span>Pemasukan</a>
                        <a href="../transaksi/pengeluaran.php" class="q-btn red"><span>💸</span>Pengeluaran</a>
                        <a href="../transaksi/transfer.php" class="q-btn purple"><span>🔁</span>Transfer</a>
                        <a href="../laporan/harian.php" class="q-btn blue"><span>📊</span>Laporan</a>
                    </div>
                </div>

                <div class="akun-card">
                    <div class="akun-card-header">
                        <h2>Akun Keuangan</h2>
                        <a href="../data/akuntf.php">Kelola →</a>
                    </div>
                    <div class="akun-list">
                        <?php if (count($akunList) > 0): foreach ($akunList as $akun):
                        $icon = $jenisIcon[$akun['jenis_akun']] ?? '💼';
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
                        <div style="text-align:center;color:#94a3b8;padding:20px;font-size:14px">Belum ada akun</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>