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
$tahun  = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Total pemasukan tahun ini
$stmtMasuk = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id = ? AND tipe = 'pemasukan' AND YEAR(tanggal) = ?");
$stmtMasuk->execute([$userId, $tahun]);
$totalMasuk = $stmtMasuk->fetch(PDO::FETCH_ASSOC)['total'];

// Total pengeluaran tahun ini
$stmtKeluar = $conn->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM transaksi WHERE user_id = ? AND tipe = 'pengeluaran' AND YEAR(tanggal) = ?");
$stmtKeluar->execute([$userId, $tahun]);
$totalKeluar = $stmtKeluar->fetch(PDO::FETCH_ASSOC)['total'];

$laba = $totalMasuk - $totalKeluar;

// Rekap per bulan
$stmtBulanan = $conn->prepare("
    SELECT 
        MONTH(tanggal) AS bulan,
        COALESCE(SUM(CASE WHEN tipe='pemasukan' THEN jumlah ELSE 0 END), 0) AS total_masuk,
        COALESCE(SUM(CASE WHEN tipe='pengeluaran' THEN jumlah ELSE 0 END), 0) AS total_keluar
    FROM transaksi
    WHERE user_id = ? AND YEAR(tanggal) = ?
    GROUP BY MONTH(tanggal)
    ORDER BY MONTH(tanggal)
");
$stmtBulanan->execute([$userId, $tahun]);
$rekapBulanan = $stmtBulanan->fetchAll(PDO::FETCH_ASSOC);

$namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Tahunan</title>
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

    .filter-card select,
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
        <h1> Laporan Tahunan</h1>

        <div class="filter-card">
            <form method="GET" style="display:flex;gap:12px;align-items:center;">
                <label style="font-size:14px;font-weight:600;">Tahun:</label>
                <select name="tahun">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $tahun ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit">Lihat</button>
            </form>
        </div>

        <div class="cards">
            <div class="card">
                <h3>Total Pemasukan <?= $tahun ?></h3>
                <div class="value green"><?= rp($totalMasuk) ?></div>
            </div>
            <div class="card">
                <h3>Total Pengeluaran <?= $tahun ?></h3>
                <div class="value red"><?= rp($totalKeluar) ?></div>
            </div>
            <div class="card">
                <h3>Laba / Rugi <?= $tahun ?></h3>
                <div class="value <?= $laba >= 0 ? 'blue' : 'red' ?>"><?= ($laba < 0 ? '-' : '') . rp(abs($laba)) ?>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="section-title">Rekap Per Bulan — Tahun <?= $tahun ?></div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Bulan</th>
                        <th>Total Pemasukan</th>
                        <th>Total Pengeluaran</th>
                        <th>Laba / Rugi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rekapBulanan) > 0): $no = 1; foreach ($rekapBulanan as $row):
                    $labaRow = $row['total_masuk'] - $row['total_keluar'];
                ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= $namaBulan[(int)$row['bulan']] ?></td>
                        <td class="green"><?= rp($row['total_masuk']) ?></td>
                        <td class="red"><?= rp($row['total_keluar']) ?></td>
                        <td style="font-weight:600;color:<?= $labaRow >= 0 ? '#2563eb' : '#dc2626' ?>">
                            <?= ($labaRow < 0 ? '-' : '') . rp(abs($labaRow)) ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5" class="empty">📭 Tidak ada transaksi tahun <?= $tahun ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>