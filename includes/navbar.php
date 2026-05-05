<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

$base = '../';
?>
<script>document.documentElement.setAttribute('data-theme',localStorage.getItem('smartkas-theme')||'dark');</script>
<link rel="stylesheet" href="<?= $base ?>assets/css/global.css">

<nav class="navbar" id="navbar">
    <div class="nav-brand">
        <a href="<?= $base ?>dashboard/index.php" class="logo">Smart<span>Kas</span></a>
    </div>

    <button class="hamburger" id="hamburgerBtn" type="button" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>

    <div class="nav-menu" id="navMenu">
        <a href="<?= $base ?>dashboard/index.php"
           class="nav-link <?= $currentDir === 'dashboard' ? 'active' : '' ?>">Dashboard</a>

        <div class="nav-dropdown <?= $currentDir === 'transaksi' ? 'active-parent' : '' ?>">
            <a class="nav-link dropdown-toggle <?= $currentDir === 'transaksi' ? 'active' : '' ?>">Transaksi</a>
            <div class="dropdown-menu">
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

        <?php if (isAdmin()): ?>
        <div class="nav-dropdown <?= in_array($currentFile, ['akuntf.php','kategoricf.php','karyawan.php']) ? 'active-parent' : '' ?>">
            <a class="nav-link dropdown-toggle <?= in_array($currentFile, ['akuntf.php','kategoricf.php','karyawan.php']) ? 'active' : '' ?>">Data</a>
            <div class="dropdown-menu">
                <a href="<?= $base ?>data/akuntf.php"
                   class="<?= $currentFile === 'akuntf.php' ? 'active' : '' ?>">Akun Keuangan</a>
                <a href="<?= $base ?>data/kategoricf.php"
                   class="<?= $currentFile === 'kategoricf.php' ? 'active' : '' ?>">Kategori</a>
                <a href="<?= $base ?>data/karyawan.php"
                   class="<?= $currentFile === 'karyawan.php' ? 'active' : '' ?>">Kelola Karyawan</a>
            </div>
        </div>

        <div class="nav-dropdown <?= $currentDir === 'laporan' ? 'active-parent' : '' ?>">
            <a class="nav-link dropdown-toggle <?= $currentDir === 'laporan' ? 'active' : '' ?>">Laporan</a>
            <div class="dropdown-menu">
                <a href="<?= $base ?>laporan/harian.php"
                   class="<?= $currentFile === 'harian.php' ? 'active' : '' ?>">Harian</a>
                <a href="<?= $base ?>laporan/bulanan.php"
                   class="<?= $currentFile === 'bulanan.php' ? 'active' : '' ?>">Bulanan</a>
                <a href="<?= $base ?>laporan/tahunan.php"
                   class="<?= $currentFile === 'tahunan.php' ? 'active' : '' ?>">Tahunan</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="nav-dropdown <?= $currentDir === 'budgeting' ? 'active-parent' : '' ?>">
            <a class="nav-link dropdown-toggle <?= $currentDir === 'budgeting' ? 'active' : '' ?>">Budgeting</a>
            <div class="dropdown-menu">
                <?php if (isAdmin()): ?>
                <a href="<?= $base ?>budgeting/index.php"
                   class="<?= $currentDir === 'budgeting' && $currentFile === 'index.php' ? 'active' : '' ?>">Kelola Budget</a>
                <?php endif; ?>
                <a href="<?= $base ?>budgeting/monitoring.php"
                   class="<?= $currentFile === 'monitoring.php' ? 'active' : '' ?>">Monitoring</a>
            </div>
        </div>

        <a href="<?= $base ?>data/user.php"
           class="nav-link <?= $currentFile === 'user.php' ? 'active' : '' ?>">Pengaturan</a>

        <a href="<?= $base ?>auth/logout.php" class="nav-link nav-logout-mobile">Keluar</a>
    </div>

    <div class="nav-right" id="navRight">
        <label class="theme-switch" id="themeToggle">
            <input type="checkbox" id="themeCheckbox">
            <span class="slider"></span>
        </label>
        <div class="nav-user">
            <span class="nav-user-name"><?= htmlspecialchars(getUserName()) ?></span>
            <span class="nav-role-badge <?= isAdmin() ? 'role-admin' : 'role-karyawan' ?>"><?= isAdmin() ? 'Admin' : 'Karyawan' ?></span>
        </div>
        <a href="<?= $base ?>auth/logout.php" class="nav-logout-btn" title="Keluar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
        </a>
    </div>
</nav>
<div class="nav-backdrop" id="navBackdrop"></div>

<script src="<?= $base ?>assets/js/theme.js"></script>
<script>
(function() {
    var hamburger = document.getElementById('hamburgerBtn');
    var navMenu = document.getElementById('navMenu');
    var navRight = document.getElementById('navRight');
    var backdrop = document.getElementById('navBackdrop');

    // Hamburger toggle (mobile)
    if (hamburger && navMenu && backdrop) {
        hamburger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            navMenu.classList.toggle('open');
            backdrop.classList.toggle('show');
            this.classList.toggle('active');
        });

        backdrop.addEventListener('click', function() {
            navMenu.classList.remove('open');
            backdrop.classList.remove('show');
            hamburger.classList.remove('active');
        });
    }

    function closeAll(except) {
        document.querySelectorAll('.nav-dropdown.open').forEach(function(d) {
            if (d !== except) d.classList.remove('open');
        });
    }

    var closeTimer = null;
    document.querySelectorAll('.nav-dropdown').forEach(function(dropdown) {
        dropdown.addEventListener('mouseenter', function() {
            if (window.innerWidth > 768) {
                clearTimeout(closeTimer);
                closeAll(this);
                this.classList.add('open');
            }
        });
        dropdown.addEventListener('mouseleave', function() {
            if (window.innerWidth > 768) {
                var self = this;
                closeTimer = setTimeout(function() {
                    self.classList.remove('open');
                }, 150);
            }
        });
    });

    document.querySelectorAll('.nav-dropdown > .dropdown-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (window.innerWidth <= 768) {
                var parent = this.parentElement;
                closeAll(parent);
                parent.classList.toggle('open');
            }
        });
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.nav-dropdown')) {
            closeAll();
        }
    });

    var links = document.querySelectorAll('.nav-menu a[href], .dropdown-menu a[href]');
    for (var i = 0; i < links.length; i++) {
        links[i].addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                navMenu.classList.remove('open');
                backdrop.classList.remove('show');
                hamburger.classList.remove('active');
            }
        });
    }
})();
</script>
