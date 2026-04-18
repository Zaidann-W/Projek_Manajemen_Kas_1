<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../services/authservice.php';
include '../config/config.php';
requireLogin();

$userId = getUserId();

// HAPUS
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmtGet = $conn->prepare("SELECT * FROM transaksi WHERE id = ? AND user_id = ?");
    $stmtGet->execute([$id, $userId]);
    $tx = $stmtGet->fetch(PDO::FETCH_ASSOC);

    if ($tx) {
        $conn->beginTransaction();
        try {
            if ($tx['tipe'] === 'pemasukan') {
                $conn->prepare("UPDATE akun_tf SET saldo_awal = saldo_awal - ? WHERE id = ? AND user_id = ?")
                    ->execute([$tx['jumlah'], $tx['akuntf_id'], $userId]);
            } elseif ($tx['tipe'] === 'pengeluaran') {
                $conn->prepare("UPDATE akun_tf SET saldo_awal = saldo_awal + ? WHERE id = ? AND user_id = ?")
                    ->execute([$tx['jumlah'], $tx['akuntf_id'], $userId]);
            }
            $conn->prepare("DELETE FROM transaksi WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
            $conn->commit();
            $_SESSION['success'] = "Transaksi berhasil dihapus & saldo dikembalikan";
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = "Gagal menghapus transaksi";
        }
    }
    header("Location: riwayat.php");
    exit;
}

// EDIT - load data
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmtEdit = $conn->prepare("SELECT * FROM transaksi WHERE id = ? AND user_id = ?");
    $stmtEdit->execute([$id, $userId]);
    $editData = $stmtEdit->fetch(PDO::FETCH_ASSOC);
}

// UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id         = (int) $_POST['edit_id'];
    $keterangan = trim($_POST['keterangan'] ?? '');
    $tanggal    = $_POST['tanggal'] ?? '';

    if (!empty($keterangan) && !empty($tanggal)) {
        $conn->prepare("UPDATE transaksi SET keterangan = ?, tanggal = ? WHERE id = ? AND user_id = ?")
            ->execute([$keterangan, $tanggal, $id, $userId]);
        $_SESSION['success'] = "Transaksi berhasil diperbarui";
        header("Location: riwayat.php");
        exit;
    }
}

// FILTER & SEARCH
$search = trim($_GET['search'] ?? '');
$tipe   = $_GET['tipe'] ?? '';
$bulan  = $_GET['bulan'] ?? '';

$where = "WHERE t.user_id = ?";
$params = [$userId];

if ($search) { $where .= " AND t.keterangan LIKE ?"; $params[] = "%$search%"; }
if ($tipe) { $where .= " AND t.tipe = ?"; $params[] = $tipe; }
if ($bulan) { $where .= " AND DATE_FORMAT(t.tanggal, '%Y-%m') = ?"; $params[] = $bulan; }

$stmtTx = $conn->prepare("
    SELECT t.*, a.nama_akun FROM transaksi t
    LEFT JOIN akun_tf a ON t.akuntf_id = a.id
    $where ORDER BY t.tanggal DESC, t.id DESC LIMIT 50
");
$stmtTx->execute($params);
$txList = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

$stmtAkun = $conn->prepare("SELECT id, nama_akun FROM akun_tf WHERE user_id = ?");
$stmtAkun->execute([$userId]);
$akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi</title>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main">
        <div class="page-header">
            <h1>Riwayat Transaksi</h1>
            <p>Lihat, cari, edit, dan hapus transaksi kamu</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); endif; ?>

        <!-- FILTER -->
        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Cari Keterangan</label>
                    <input type="text" name="search" placeholder="Cari transaksi..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="filter-group">
                    <label>Tipe</label>
                    <select name="tipe">
                        <option value="">Semua Tipe</option>
                        <option value="pemasukan" <?= $tipe==='pemasukan'?'selected':'' ?>>Pemasukan</option>
                        <option value="pengeluaran" <?= $tipe==='pengeluaran'?'selected':'' ?>>Pengeluaran</option>
                        <option value="transfer" <?= $tipe==='transfer'?'selected':'' ?>>Transfer</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Bulan</label>
                    <input type="month" name="bulan" value="<?= $bulan ?>">
                </div>
                <button type="submit" class="btn-filter">Cari</button>
                <a href="riwayat.php" class="btn-reset">Reset</a>
            </form>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <div class="table-header">
                <h2>Daftar Transaksi</h2>
                <span class="result-count"><?= count($txList) ?> transaksi ditemukan</span>
            </div>
            <table>
                <thead>
                    <tr><th>Tanggal</th><th>Keterangan</th><th>Akun</th><th>Tipe</th><th>Nominal</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php if (count($txList) > 0): foreach ($txList as $tx): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($tx['tanggal'])) ?></td>
                        <td><?= $tx['keterangan'] ? htmlspecialchars($tx['keterangan']) : '<span style="color:var(--text-muted)">-</span>' ?></td>
                        <td><?= htmlspecialchars($tx['nama_akun'] ?? '-') ?></td>
                        <td><span class="badge <?= $tx['tipe'] ?>"><?= $tx['tipe'] ?></span></td>
                        <td class="<?= $tx['tipe']==='pemasukan'?'green':($tx['tipe']==='pengeluaran'?'red':'') ?>" style="font-weight:700">
                            <?= ($tx['tipe']==='pemasukan'?'+':($tx['tipe']==='pengeluaran'?'-':'')) . rp($tx['jumlah']) ?>
                        </td>
                        <td>
                            <a href="#" class="action-btn btn-edit"
                                onclick="openEdit(<?= $tx['id'] ?>, '<?= htmlspecialchars($tx['keterangan'], ENT_QUOTES) ?>', '<?= $tx['tanggal'] ?>', '<?= $tx['tipe'] ?>', <?= $tx['jumlah'] ?>); return false;">Edit</a>
                            <?php if ($tx['tipe'] !== 'transfer'): ?>
                            <a href="?delete=<?= $tx['id'] ?>" class="action-btn btn-delete"
                                onclick="return confirm('Yakin hapus? Saldo akun akan dikembalikan.')">Hapus</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6" class="empty">Tidak ada transaksi ditemukan</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL EDIT -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal">
            <h2>Edit Transaksi</h2>
            <div class="modal-info" id="modalInfo">-</div>
            <form method="POST">
                <input type="hidden" name="edit_id" id="editId">
                <div class="form-group">
                    <label>Keterangan</label>
                    <textarea name="keterangan" id="editKeterangan" placeholder="Keterangan transaksi"></textarea>
                </div>
                <div class="form-group">
                    <label>Tanggal</label>
                    <input type="date" name="tanggal" id="editTanggal">
                </div>
                <div class="modal-btns">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn-save">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEdit(id, keterangan, tanggal, tipe, jumlah) {
        document.getElementById('editId').value = id;
        document.getElementById('editKeterangan').value = keterangan;
        document.getElementById('editTanggal').value = tanggal;
        document.getElementById('modalInfo').textContent =
            'Tipe: ' + tipe + ' | Nominal: Rp ' + parseInt(jumlah).toLocaleString('id-ID');
        document.getElementById('modalOverlay').classList.add('show');
    }
    function closeModal() { document.getElementById('modalOverlay').classList.remove('show'); }
    document.getElementById('modalOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    </script>
</body>
</html>