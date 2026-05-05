<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('PARTIAL_MODE', true);
include __DIR__ . '/../services/authservice.php';
include __DIR__ . '/../config/config.php';
requireLogin();

$activeTab = $_GET['tab'] ?? 'pemasukan';
if (!in_array($activeTab, ['pemasukan','pengeluaran','transfer','riwayat'])) $activeTab = 'pemasukan';

ob_start();
include __DIR__ . "/$activeTab.php";
$tabContent = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main">
        <div class="page-header">
            <h1>Transaksi</h1>
        </div>

        <div class="tab-nav">
            <a href="?tab=pemasukan" class="tab-btn <?= $activeTab === 'pemasukan' ? 'active green-tab' : '' ?>">Pemasukan</a>
            <a href="?tab=pengeluaran" class="tab-btn <?= $activeTab === 'pengeluaran' ? 'active red-tab' : '' ?>">Pengeluaran</a>
            <a href="?tab=transfer" class="tab-btn <?= $activeTab === 'transfer' ? 'active purple-tab' : '' ?>">Transfer</a>
            <a href="?tab=riwayat" class="tab-btn <?= $activeTab === 'riwayat' ? 'active blue-tab' : '' ?>">Riwayat</a>
        </div>

        <div class="tab-content">
            <?= $tabContent ?>
        </div>
    </div>
</body>
</html>
