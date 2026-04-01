<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$bulan  = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$parts  = explode('-', $bulan);
$tahun  = $parts[0];
$bln    = $parts[1];

// Total pemasukan
$stmtMasuk = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id = ? AND tipe = 'pemasukan' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmtMasuk->execute([$userId, $bln, $tahun]);
$totalMasuk = $stmtMasuk->fetch(PDO::FETCH_ASSOC)['total'];

// Total pengeluaran
$stmtKeluar = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id = ? AND tipe = 'pengeluaran' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmtKeluar->execute([$userId, $bln, $tahun]);
$totalKeluar = $stmtKeluar->fetch(PDO::FETCH_ASSOC)['total'];

$labaRugi = $totalMasuk - $totalKeluar;
$status   = $labaRugi >= 0 ? 'LABA' : 'RUGI';

// Detail pemasukan
$stmtDetailMasuk = $conn->prepare("
    SELECT t.*, a.nama_akun FROM transaksi t 
    LEFT JOIN akun_tf a ON t.akuntf_id = a.id 
    WHERE t.user_id = ? AND t.tipe = 'pemasukan' AND MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ?
    ORDER BY t.tanggal DESC
");
$stmtDetailMasuk->execute([$userId, $bln, $tahun]);
$detailMasuk = $stmtDetailMasuk->fetchAll(PDO::FETCH_ASSOC);

// Detail pengeluaran
$stmtDetailKeluar = $conn->prepare("
    SELECT t.*, a.nama_akun FROM transaksi t 
    LEFT JOIN akun_tf a ON t.akuntf_id = a.id 
    WHERE t.user_id = ? AND t.tipe = 'pengeluaran' AND MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ?
    ORDER BY t.tanggal DESC
");
$stmtDetailKeluar->execute([$userId, $bln, $tahun]);
$detailKeluar = $stmtDetailKeluar->fetchAll(PDO::FETCH_ASSOC);

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Laba Rugi</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: Segoe UI
    }

    body {
        background: #f4f6fb;
        display: flex
    }

    .logo {
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 40px;
    }

    .sidebar a:hover {
        background: #1f2937;
        color: #fff
    }

    .main {
        flex: 1;
        padding: 30px;
    }

    h1 {
        margin-bottom: 25px;
    }

    .filter-card {
        background: #fff;
        padding: 20px;
        border-radius: 14px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .05);
        margin-bottom: 25px;
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .filter-card input {
        padding: 9px 12px;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 14px;
    }

    .filter-card button {
        padding: 9px 18px;
        background: #2563eb;
        color: #fff;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
    }

    .laba-card {
        background: #fff;
        padding: 28px;
        border-radius: 14px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .05);
        margin-bottom: 25px;
    }

    .laba-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f3f4f6;
        font-size: 15px;
    }

    .laba-row:last-child {
        border: none;
    }

    .laba-row.total {
        font-weight: 700;
        font-size: 17px;
        padding-top: 16px;
    }

    .laba-row.total.laba {
        color: #16a34a
    }

    .laba-row.total.rugi {
        color: #dc2626
    }

    .green {
        color: #16a34a
    }

    .red {
        color: #dc2626
    }

    .status-badge {
        display: inline-block;
        padding: 6px 18px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 700;
        margin-top: 8px;
    }

    .status-badge.laba {
        background: #dcfce7;
        color: #16a34a
    }

    .status-badge.rugi {
        background: #fee2e2;
        color: #dc2626
    }

    .grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
    }

    .table-card {
        background: #fff;
        padding: 22px;
        border-radius: 14px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .05);
    }

    .section-title {
        font-size: 15px;
        font-weight: 600;
        color: #111;
        margin-bottom: 12px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        padding: 10px 14px;
        text-align: left;
        font-size: 12px;
        color: #9ca3af;
        text-transform: uppercase;
        border-bottom: 2px solid #f3f4f6;
    }

    td {
        padding: 11px 14px;
        border-bottom: 1px solid #f9fafb;
        font-size: 13px;
    }

    tr:hover td {
        background: #fafafa
    }

    .empty {
        text-align: center;
        color: #9ca3af;
        padding: 24px;
        font-size: 13px;
    }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main">
        <h1> Laporan Laba Rugi</h1>

        <div class="filter-card">
            <form method="GET" style="display:flex;gap:12px;align-items:center;">
                <label style="font-size:14px;font-weight:600;">Bulan:</label>
                <input type="month" name="bulan" value="<?= $bulan ?>">
                <button type="submit">Lihat</button>
            </form>
        </div>

        <!-- RINGKASAN LABA RUGI -->
        <div class="laba-card">
            <h2 style="margin-bottom:16px;font-size:16px;">Ringkasan — <?= date('F Y', strtotime($bulan . '-01')) ?>
            </h2>
            <div class="laba-row">
                <span>Total Pemasukan</span>
                <span class="green"><?= rp($totalMasuk) ?></span>
            </div>
            <div class="laba-row">
                <span>Total Pengeluaran</span>
                <span class="red">(<?= rp($totalKeluar) ?>)</span>
            </div>
            <div class="laba-row total <?= strtolower($status) ?>">
                <span><?= $status ?> BERSIH</span>
                <span><?= ($labaRugi < 0 ? '-' : '') . rp(abs($labaRugi)) ?></span>
            </div>
            <span class="status-badge <?= strtolower($status) ?>">
                <?= $status === 'LABA' ? '✅ Untung' : '⚠️ Rugi' ?>
            </span>
        </div>

        <!-- DETAIL PEMASUKAN & PENGELUARAN -->
        <div class="grid">
            <div class="table-card">
                <div class="section-title" style="color:#16a34a">📈 Detail Pemasukan</div>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($detailMasuk) > 0): foreach ($detailMasuk as $row): ?>
                        <tr>
                            <td><?= date('d/m', strtotime($row['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($row['keterangan']) ?></td>
                            <td class="green">+<?= rp($row['jumlah']) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="3" class="empty">Tidak ada pemasukan</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <div class="section-title" style="color:#dc2626">📉 Detail Pengeluaran</div>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($detailKeluar) > 0): foreach ($detailKeluar as $row): ?>
                        <tr>
                            <td><?= date('d/m', strtotime($row['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($row['keterangan']) ?></td>
                            <td class="red">-<?= rp($row['jumlah']) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="3" class="empty">Tidak ada pengeluaran</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>