<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('PARTIAL_MODE', true);
include __DIR__ . '/../services/authservice.php';
include __DIR__ . '/../config/config.php';
requireLogin();
requireAdmin();

$activeTab = $_GET['tab'] ?? 'harian';
if (!in_array($activeTab, ['harian','bulanan','tahunan'])) $activeTab = 'harian';

ob_start();
include __DIR__ . "/$activeTab.php";
$tabContent = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main">
        <div class="page-header">
            <div>
                <h1>Laporan</h1>
            </div>
            <div class="export-btns">
                <a href="#" id="btnExportPdf" class="export-btn pdf-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Export PDF
                </a>
                <a href="#" id="btnExportCsv" class="export-btn csv-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export CSV
                </a>
            </div>
        </div>
        <div class="tab-nav">
            <a href="?tab=harian" class="tab-btn <?= $activeTab === 'harian' ? 'active green-tab' : '' ?>">Harian</a>
            <a href="?tab=bulanan" class="tab-btn <?= $activeTab === 'bulanan' ? 'active blue-tab' : '' ?>">Bulanan</a>
            <a href="?tab=tahunan" class="tab-btn <?= $activeTab === 'tahunan' ? 'active purple-tab' : '' ?>">Tahunan</a>
        </div>
        <div class="tab-content"><?= $tabContent ?></div>
    </div>

<script>
function updateExportLinks() {
    var tab = '<?= $activeTab ?>';
    var pdfUrl = 'export_pdf.php?tipe=' + tab;
    var csvUrl = 'export.php?';

    var form = document.querySelector('.filter-form');
    if (form) {
        var inputs = form.querySelectorAll('input, select');
        inputs.forEach(function(el) {
            if (el.name && el.name !== 'tab' && el.value) {
                pdfUrl += '&' + el.name + '=' + encodeURIComponent(el.value);
                csvUrl += '&' + el.name + '=' + encodeURIComponent(el.value);
            }
        });
    }

    if (tab === 'harian') {
        var tglInput = form ? form.querySelector('[name=tanggal]') : null;
        if (tglInput) pdfUrl = 'export_pdf.php?tipe=harian&tanggal=' + tglInput.value;
    } else if (tab === 'bulanan') {
        var blnInput = form ? form.querySelector('[name=bulan]') : null;
        if (blnInput) {
            pdfUrl = 'export_pdf.php?tipe=bulanan&bulan=' + blnInput.value;
            csvUrl = 'export.php?bulan=' + blnInput.value;
        }
    } else if (tab === 'tahunan') {
        var thnInput = form ? form.querySelector('[name=tahun]') : null;
        if (thnInput) pdfUrl = 'export_pdf.php?tipe=tahunan&tahun=' + thnInput.value;
    }

    document.getElementById('btnExportPdf').href = pdfUrl;
    document.getElementById('btnExportCsv').href = csvUrl;
}
updateExportLinks();

var filterForm = document.querySelector('.filter-form');
if (filterForm) {
    filterForm.addEventListener('change', updateExportLinks);
}
</script>
</body>
</html>
