<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('PARTIAL_MODE', true);
include __DIR__ . '/../services/authservice.php';
include __DIR__ . '/../config/config.php';
requireLogin();

$defaultTab = isAdmin() ? 'kelola' : 'monitoring';
$activeTab = $_GET['tab'] ?? $defaultTab;
if (!in_array($activeTab, ['kelola','monitoring'])) $activeTab = $defaultTab;

if ($activeTab === 'kelola' && !isAdmin()) {
    header("Location: index.php?tab=monitoring");
    exit;
}

ob_start();
include __DIR__ . "/$activeTab.php";
$tabContent = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgeting</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main">
        <div class="page-header">
            <h1>Budgeting</h1>
        </div>
        <div class="tab-nav">
            <?php if (isAdmin()): ?>
            <a href="?tab=kelola" class="tab-btn <?= $activeTab === 'kelola' ? 'active blue-tab' : '' ?>">Kelola Budget</a>
            <?php endif; ?>
            <a href="?tab=monitoring" class="tab-btn <?= $activeTab === 'monitoring' ? 'active green-tab' : '' ?>">Monitoring</a>
        </div>
        <div class="tab-content"><?= $tabContent ?></div>
    </div>
</body>
</html>
