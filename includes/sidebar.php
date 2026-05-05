<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

$base = '../';
?>
<script>document.documentElement.setAttribute('data-theme',localStorage.getItem('smartkas-theme')||'dark');</script>
<link rel="stylesheet" href="<?= $base ?>assets/css/global.css?v=<?= time() ?>">

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

    <a href="<?= $base ?>transaksi/index.php" class="<?= $currentDir === 'transaksi' ? 'active' : '' ?>">
        Transaksi
    </a>

    <?php if (isAdmin()): ?>
    <a href="<?= $base ?>data/index.php" class="<?= $currentDir === 'data' && $currentFile === 'index.php' ? 'active' : '' ?>">
        Data
    </a>

    <a href="<?= $base ?>laporan/index.php" class="<?= $currentDir === 'laporan' ? 'active' : '' ?>">
        Laporan
    </a>
    <?php endif; ?>

    <a href="<?= $base ?>budgeting/index.php" class="<?= $currentDir === 'budgeting' ? 'active' : '' ?>">
        Budgeting
    </a>

    <a href="<?= $base ?>data/user.php" class="<?= $currentFile === 'user.php' ? 'active' : '' ?>">
        Pengaturan
    </a>

    <div class="sidebar-footer">
        <div class="sidebar-user-info">
            <span class="sidebar-user-name"><?= htmlspecialchars(getUserName()) ?></span>
            <span class="sidebar-role-badge <?= isAdmin() ? 'role-admin' : 'role-karyawan' ?>"><?= isAdmin() ? 'Admin' : 'Karyawan' ?></span>
        </div>
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
    document.querySelectorAll('.menu-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            this.parentElement.classList.toggle('open');
        });
    });

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