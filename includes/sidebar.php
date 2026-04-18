<?php
// Deteksi halaman aktif
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

// Base path relatif tergantung folder
$base = '../';
?>
<script>document.documentElement.setAttribute('data-theme',localStorage.getItem('smartkas-theme')||'dark');</script>
<link rel="stylesheet" href="<?= $base ?>assets/css/global.css">

<button class="hamburger" id="hamburgerBtn" type="button" aria-label="Menu">
    <span></span><span></span><span></span>
</button>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-top">
        <div class="logo">Smart<span>Kas</span></div>
        <label class="theme-switch" id="themeToggle">
            <input type="checkbox" id="themeCheckbox">
            <span class="slider"></span>
        </label>
    </div>

    <a href="<?= $base ?>dashboard/index.php" class="<?= $currentDir === 'dashboard' ? 'active' : '' ?>">
        Dashboard
    </a>

    <!-- TRANSAKSI -->
    <div class="menu-item <?= $currentDir === 'transaksi' ? 'open' : '' ?>">
        <a class="menu-toggle">Transaksi</a>
        <div class="submenu">
            <a href="<?= $base ?>transaksi/pemasukan.php"
                class="<?= $currentFile === 'pemasukan.php' ? 'active' : '' ?>">Pemasukan</a>
            <a href="<?= $base ?>transaksi/pengeluaran.php"
                class="<?= $currentFile === 'pengeluaran.php' ? 'active' : '' ?>">Pengeluaran</a>
            <a href="<?= $base ?>transaksi/transfer.php"
                class="<?= $currentFile === 'transfer.php' ? 'active' : '' ?>">Transfer</a>
            <a href="<?= $base ?>transaksi/riwayat.php"
                class="<?= $currentFile === 'riwayat.php' ? 'active' : '' ?>">Riwayat</a>
        </div>
    </div>

    <!-- DATA -->
    <div class="menu-item <?= in_array($currentFile, ['akuntf.php','kategoricf.php']) ? 'open' : '' ?>">
        <a class="menu-toggle">Data</a>
        <div class="submenu">
            <a href="<?= $base ?>data/akuntf.php" class="<?= $currentFile === 'akuntf.php' ? 'active' : '' ?>">Akun
                Keuangan</a>
            <a href="<?= $base ?>data/kategoricf.php"
                class="<?= $currentFile === 'kategoricf.php' ? 'active' : '' ?>">Kategori</a>
        </div>
    </div>

    <!-- LAPORAN -->
    <div class="menu-item <?= $currentDir === 'laporan' ? 'open' : '' ?>">
        <a class="menu-toggle">Laporan</a>
        <div class="submenu">
            <a href="<?= $base ?>laporan/harian.php"
                class="<?= $currentFile === 'harian.php' ? 'active' : '' ?>">Harian</a>
            <a href="<?= $base ?>laporan/bulanan.php"
                class="<?= $currentFile === 'bulanan.php' ? 'active' : '' ?>">Bulanan</a>
            <a href="<?= $base ?>laporan/tahunan.php"
                class="<?= $currentFile === 'tahunan.php' ? 'active' : '' ?>">Tahunan</a>
        </div>
    </div>

    <!-- BUDGETING -->
    <div class="menu-item <?= $currentDir === 'budgeting' ? 'open' : '' ?>">
        <a class="menu-toggle">Budgeting</a>
        <div class="submenu">
            <a href="<?= $base ?>budgeting/index.php"
                class="<?= $currentDir === 'budgeting' && $currentFile === 'index.php' ? 'active' : '' ?>">Kelola Budget</a>
            <a href="<?= $base ?>budgeting/monitoring.php"
                class="<?= $currentFile === 'monitoring.php' ? 'active' : '' ?>">Monitoring</a>
        </div>
    </div>

    <a href="<?= $base ?>data/user.php" class="<?= $currentFile === 'user.php' ? 'active' : '' ?>">
        Pengaturan
    </a>

    <div class="sidebar-footer">
        <a href="<?= $base ?>auth/logout.php" class="logout-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Keluar
        </a>
    </div>
</div>

<script src="<?= $base ?>assets/js/theme.js"></script>
<script>
(function() {
    // Submenu toggles
    document.querySelectorAll('.menu-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            this.parentElement.classList.toggle('open');
        });
    });

    // Mobile sidebar toggle
    var hamburger = document.getElementById('hamburgerBtn');
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');

    if (hamburger && sidebar && backdrop) {
        hamburger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            sidebar.classList.toggle('open');
            backdrop.classList.toggle('show');
            this.classList.toggle('active');
        });

        backdrop.addEventListener('click', function() {
            sidebar.classList.remove('open');
            backdrop.classList.remove('show');
            hamburger.classList.remove('active');
        });

        // Close on link click (mobile)
        var links = sidebar.querySelectorAll('a[href]');
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('open');
                    backdrop.classList.remove('show');
                    hamburger.classList.remove('active');
                }
            });
        }
    }
})();
</script>