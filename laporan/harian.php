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
$today = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Total pemasukan hari ini
$stmtMasuk = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id = ? AND tipe = 'pemasukan' AND tanggal = ?");
$stmtMasuk->execute([$userId, $today]);
$totalMasuk = $stmtMasuk->fetch(PDO::FETCH_ASSOC)['total'];

// Total pengeluaran hari ini
$stmtKeluar = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id = ? AND tipe = 'pengeluaran' AND tanggal = ?");
$stmtKeluar->execute([$userId, $today]);
$totalKeluar = $stmtKeluar->fetch(PDO::FETCH_ASSOC)['total'];

$saldo = $totalMasuk - $totalKeluar;

// Detail transaksi hari ini
$stmtDetail = $conn->prepare("
    SELECT t.*, a.nama_akun 
    FROM transaksi t 
    LEFT JOIN akun_tf a ON t.akuntf_id = a.id 
    WHERE t.user_id = ? AND t.tanggal = ? 
    ORDER BY t.id DESC
");
$stmtDetail->execute([$userId, $today]);
$detail = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Harian</title>
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

    .cards {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        margin-bottom: 25px;
    }

    .card {
        background: #fff;
        padding: 20px;
        border-radius: 14px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .05);
    }

    .card h3 {
        font-size: 13px;
        color: #9ca3af;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .card .value {
        font-size: 20px;
        font-weight: 700;
    }

    .green {
        color: #16a34a
    }

    .red {
        color: #dc2626
    }

    .blue {
        color: #2563eb
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

    .table-card {
        background: #fff;
        padding: 22px;
        border-radius: 14px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .05);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
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
        padding: 12px 14px;
        border-bottom: 1px solid #f9fafb;
        font-size: 14px;
    }

    tr:hover td {
        background: #fafafa
    }

    .badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge.pemasukan {
        background: #dcfce7;
        color: #16a34a
    }

    .badge.pengeluaran {
        background: #fee2e2;
        color: #dc2626
    }

    .badge.transfer {
        background: #ede9fe;
        color: #7c3aed
    }

    .empty {
        text-align: center;
        color: #9ca3af;
        padding: 32px;
        font-size: 14px;
    }

    .section-title {
        font-size: 16px;
        font-weight: 600;
        color: #111;
    }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main">
        <h1> Laporan Harian</h1>

        <!-- FILTER -->
        <div class="filter-card">
            <form method="GET" style="display:flex;gap:12px;align-items:center;">
                <label style="font-size:14px;font-weight:600;">Tanggal:</label>
                <input type="date" name="tanggal" value="<?= $today ?>">
                <button type="submit">Lihat</button>
            </form>
        </div>

        <!-- SUMMARY -->
        <div class="cards">
            <div class="card">
                <h3>Total Pemasukan</h3>
                <div class="value green"><?= rp($totalMasuk) ?></div>
            </div>
            <div class="card">
                <h3>Total Pengeluaran</h3>
                <div class="value red"><?= rp($totalKeluar) ?></div>
            </div>
            <div class="card">
                <h3>Saldo Hari Ini</h3>
                <div class="value <?= $saldo >= 0 ? 'blue' : 'red' ?>"><?= rp(abs($saldo)) ?></div>
            </div>
        </div>

        <!-- DETAIL -->
        <div class="table-card">
            <div class="section-title">Detail Transaksi — <?= date('d F Y', strtotime($today)) ?></div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Keterangan</th>
                        <th>Akun</th>
                        <th>Tipe</th>
                        <th>Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($detail) > 0): $no = 1; foreach ($detail as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['keterangan']) ?></td>
                        <td><?= htmlspecialchars($row['nama_akun'] ?? '-') ?></td>
                        <td><span class="badge <?= $row['tipe'] ?>"><?= $row['tipe'] ?></span></td>
                        <td
                            style="font-weight:600;color:<?= $row['tipe']==='pemasukan' ? '#16a34a' : ($row['tipe']==='pengeluaran' ? '#dc2626' : '#7c3aed') ?>">
                            <?= ($row['tipe']==='pemasukan' ? '+' : ($row['tipe']==='pengeluaran' ? '-' : '')) . rp($row['jumlah']) ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5" class="empty">📭 Tidak ada transaksi hari ini</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>