<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('PARTIAL_MODE', true);
include __DIR__ . '/../services/authservice.php';
include __DIR__ . '/../config/config.php';
requireLogin();
requireAdmin();

$activeTab = $_GET['tab'] ?? 'akun';
if (!in_array($activeTab, ['akun','kategori','karyawan'])) $activeTab = 'akun';

$tabFile = ['akun'=>'akuntf.php','kategori'=>'kategoricf.php','karyawan'=>'karyawan.php'];
ob_start();
include __DIR__ . '/' . $tabFile[$activeTab];
$tabContent = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data</title>
</head>
<body>

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main">
        <div class="page-header">
            <h1>Data</h1>
        </div>
        <div class="tab-nav">
            <a href="?tab=akun" class="tab-btn <?= $activeTab === 'akun' ? 'active blue-tab' : '' ?>">Akun Keuangan</a>
            <a href="?tab=kategori" class="tab-btn <?= $activeTab === 'kategori' ? 'active blue-tab' : '' ?>">Kategori</a>
            <a href="?tab=karyawan" class="tab-btn <?= $activeTab === 'karyawan' ? 'active blue-tab' : '' ?>">Kelola Karyawan</a>
        </div>
        <div class="tab-content"><?= $tabContent ?></div>
    </div>
</body>
</html>
