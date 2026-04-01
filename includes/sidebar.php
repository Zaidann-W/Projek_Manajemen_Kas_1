<?php
// Deteksi halaman aktif
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

// Base path relatif tergantung folder
$base = '../';
?>
<style>
.sidebar {
    width: 220px;
    background: #111827;
    color: #fff;
    min-height: 100vh;
    padding: 25px 15px;
    flex-shrink: 0;
}

.sidebar .logo {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 30px;
    padding: 0 8px;
}

.sidebar>a,
.sidebar .menu-item>.menu-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    color: #cbd5e1;
    text-decoration: none;
    margin-bottom: 4px;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
}

.sidebar>a:hover,
.sidebar .menu-item>.menu-toggle:hover {
    background: #1f2937;
    color: #fff;
}

.sidebar .active {
    background: #2563eb !important;
    color: #fff !important;
}

.menu-item>.menu-toggle::after {
    content: "›";
    font-size: 16px;
    transition: transform .25s;
}

.menu-item.open>.menu-toggle::after {
    transform: rotate(90deg);
}

.submenu {
    display: none;
    margin: 2px 0 6px 10px;
    border-left: 2px solid #374151;
    padding-left: 8px;
}

.menu-item.open .submenu {
    display: block;
}

.submenu a {
    display: block;
    padding: 7px 10px;
    color: #94a3b8;
    text-decoration: none;
    border-radius: 6px;
    font-size: 13px;
    margin-bottom: 2px;
}

.submenu a:hover {
    background: #1f2937;
    color: #fff;
}

.submenu a.active {
    background: #2563eb !important;
    color: #fff !important;
}
</style>

<div class="sidebar">
    <div class="logo">💰 SmartKas</div>

    <a href="<?= $base ?>dashboard/index.php" class="<?= $currentDir === 'dashboard' ? 'active' : '' ?>">
        🏠 Dashboard
    </a>

    <!-- TRANSAKSI -->
    <div class="menu-item <?= $currentDir === 'transaksi' ? 'open' : '' ?>">
        <a class="menu-toggle">💳 Transaksi</a>
        <div class="submenu">
            <a href="<?= $base ?>transaksi/pemasukan.php"
                class="<?= $currentFile === 'pemasukan.php' ? 'active' : '' ?>">💹 Pemasukan</a>
            <a href="<?= $base ?>transaksi/pengeluaran.php"
                class="<?= $currentFile === 'pengeluaran.php' ? 'active' : '' ?>">💸 Pengeluaran</a>
            <a href="<?= $base ?>transaksi/transfer.php"
                class="<?= $currentFile === 'transfer.php' ? 'active' : '' ?>">🔁 Transfer</a>
            <a href="<?= $base ?>transaksi/riwayat.php" class="<?= $currentFile === 'riwayat.php' ? 'active' : '' ?>">📋
                Riwayat</a>
        </div>
    </div>

    <!-- DATA -->
    <div class="menu-item <?= in_array($currentFile, ['akuntf.php','kategoricf.php']) ? 'open' : '' ?>">
        <a class="menu-toggle">📁 Data</a>
        <div class="submenu">
            <a href="<?= $base ?>data/akuntf.php" class="<?= $currentFile === 'akuntf.php' ? 'active' : '' ?>">Akun
                Keuangan</a>
            <a href="<?= $base ?>data/kategoricf.php"
                class="<?= $currentFile === 'kategoricf.php' ? 'active' : '' ?>">Kategori</a>
        </div>
    </div>

    <!-- LAPORAN -->
    <div class="menu-item <?= $currentDir === 'laporan' ? 'open' : '' ?>">
        <a class="menu-toggle">📊 Laporan</a>
        <div class="submenu">
            <a href="<?= $base ?>laporan/harian.php"
                class="<?= $currentFile === 'harian.php' ? 'active' : '' ?>">Harian</a>
            <a href="<?= $base ?>laporan/bulanan.php"
                class="<?= $currentFile === 'bulanan.php' ? 'active' : '' ?>">Bulanan</a>
            <a href="<?= $base ?>laporan/tahunan.php"
                class="<?= $currentFile === 'tahunan.php' ? 'active' : '' ?>">Tahunan</a>
            <a href="<?= $base ?>laporan/laba-rugi.php"
                class="<?= $currentFile === 'laba-rugi.php' ? 'active' : '' ?>">Laba Rugi</a>
        </div>
    </div>

    <a href="<?= $base ?>data/user.php" class="<?= $currentFile === 'user.php' ? 'active' : '' ?>">
        ⚙️ Pengaturan
    </a>

    <a href="<?= $base ?>auth/logout.php">🚪 Logout</a>
</div>

<script>
document.querySelectorAll('.menu-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.parentElement.classList.toggle('open');
    });
});
</script>