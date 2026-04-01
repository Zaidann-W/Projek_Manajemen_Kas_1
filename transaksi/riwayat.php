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

// HAPUS
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    // Ambil data transaksi dulu buat rollback saldo
    $stmtGet = $conn->prepare("SELECT * FROM transaksi WHERE id = ? AND user_id = ?");
    $stmtGet->execute([$id, $userId]);
    $tx = $stmtGet->fetch(PDO::FETCH_ASSOC);

    if ($tx) {
        $conn->beginTransaction();
        try {
            // Kembalikan saldo
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

if ($search) {
    $where .= " AND t.keterangan LIKE ?";
    $params[] = "%$search%";
}
if ($tipe) {
    $where .= " AND t.tipe = ?";
    $params[] = $tipe;
}
if ($bulan) {
    $where .= " AND DATE_FORMAT(t.tanggal, '%Y-%m') = ?";
    $params[] = $bulan;
}

$stmtTx = $conn->prepare("
    SELECT t.*, a.nama_akun
    FROM transaksi t
    LEFT JOIN akun_tf a ON t.akuntf_id = a.id
    $where
    ORDER BY t.tanggal DESC, t.id DESC
    LIMIT 50
");
$stmtTx->execute($params);
$txList = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

// Ambil akun untuk form edit
$stmtAkun = $conn->prepare("SELECT id, nama_akun FROM akun_tf WHERE user_id = ?");
$stmtAkun->execute([$userId]);
$akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Riwayat Transaksi</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', sans-serif
    }

    body {
        background: #f1f5f9;
        display: flex;
        min-height: 100vh
    }

    .main {
        flex: 1;
        padding: 30px 36px
    }

    .page-header {
        margin-bottom: 24px
    }

    .page-header h1 {
        font-size: 22px;
        font-weight: 700;
        color: #0f172a
    }

    .page-header p {
        font-size: 14px;
        color: #64748b;
        margin-top: 4px
    }

    .alert {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 18px;
        font-size: 14px;
        font-weight: 500
    }

    .alert-success {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0
    }

    .alert-error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca
    }

    /* FILTER */
    .filter-card {
        background: #fff;
        padding: 18px 22px;
        border-radius: 16px;
        box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
        margin-bottom: 20px
    }

    .filter-form {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: flex-end
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px
    }

    .filter-group label {
        font-size: 12px;
        font-weight: 600;
        color: #64748b
    }

    .filter-group input,
    .filter-group select {
        padding: 9px 13px;
        border-radius: 9px;
        border: 1.5px solid #e2e8f0;
        font-size: 14px;
        background: #fff
    }

    .filter-group input:focus,
    .filter-group select:focus {
        outline: none;
        border-color: #2563eb
    }

    .btn-filter {
        padding: 9px 18px;
        background: #2563eb;
        color: #fff;
        border: none;
        border-radius: 9px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        align-self: flex-end
    }

    .btn-reset {
        padding: 9px 14px;
        background: #f1f5f9;
        color: #64748b;
        border: none;
        border-radius: 9px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        align-self: flex-end
    }

    /* TABLE */
    .table-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
        overflow: hidden
    }

    .table-header {
        padding: 18px 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f1f5f9
    }

    .table-header h2 {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a
    }

    .result-count {
        font-size: 13px;
        color: #64748b
    }

    table {
        width: 100%;
        border-collapse: collapse
    }

    th {
        padding: 11px 18px;
        text-align: left;
        font-size: 11px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: .5px;
        border-bottom: 1px solid #f1f5f9
    }

    td {
        padding: 13px 18px;
        font-size: 14px;
        border-bottom: 1px solid #f8fafc;
        color: #334155
    }

    tr:last-child td {
        border: none
    }

    tr:hover td {
        background: #fafbff
    }

    .badge {
        display: inline-flex;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600
    }

    .badge.pemasukan {
        background: #f0fdf4;
        color: #16a34a
    }

    .badge.pengeluaran {
        background: #fef2f2;
        color: #dc2626
    }

    .badge.transfer {
        background: #f5f3ff;
        color: #7c3aed
    }

    .action-btn {
        padding: 5px 12px;
        border-radius: 7px;
        text-decoration: none;
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        margin-right: 4px;
        display: inline-block
    }

    .btn-edit {
        background: #2563eb
    }

    .btn-delete {
        background: #dc2626
    }

    .empty {
        text-align: center;
        color: #94a3b8;
        padding: 40px;
        font-size: 14px
    }

    /* MODAL EDIT */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .4);
        z-index: 999;
        align-items: center;
        justify-content: center
    }

    .modal-overlay.show {
        display: flex
    }

    .modal {
        background: #fff;
        border-radius: 16px;
        padding: 28px;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .2)
    }

    .modal h2 {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 20px;
        color: #0f172a
    }

    .modal .form-group {
        margin-bottom: 16px
    }

    .modal label {
        font-size: 13px;
        font-weight: 600;
        color: #475569;
        display: block;
        margin-bottom: 6px
    }

    .modal input,
    .modal textarea {
        width: 100%;
        padding: 10px 13px;
        border-radius: 9px;
        border: 1.5px solid #e2e8f0;
        font-size: 14px
    }

    .modal input:focus,
    .modal textarea:focus {
        outline: none;
        border-color: #2563eb
    }

    .modal textarea {
        resize: none;
        height: 80px
    }

    .modal-info {
        background: #f1f5f9;
        border-radius: 9px;
        padding: 10px 14px;
        font-size: 13px;
        color: #475569;
        margin-bottom: 16px
    }

    .modal-btns {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px
    }

    .modal-btns button {
        padding: 10px 20px;
        border-radius: 9px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600
    }

    .btn-save {
        background: #2563eb;
        color: #fff
    }

    .btn-cancel {
        background: #f1f5f9;
        color: #64748b
    }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main">
        <div class="page-header">
            <h1> Riwayat Transaksi</h1>
            <p>Lihat, cari, edit, dan hapus transaksi kamu</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">✅ <?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">⚠️ <?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- FILTER -->
        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Cari Keterangan</label>
                    <input type="text" name="search" placeholder="Cari transaksi..."
                        value="<?= htmlspecialchars($search) ?>">
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
                <button type="submit" class="btn-filter">🔍 Cari</button>
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
                    <tr>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th>Akun</th>
                        <th>Tipe</th>
                        <th>Nominal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($txList) > 0): foreach ($txList as $tx): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($tx['tanggal'])) ?></td>
                        <td><?= $tx['keterangan'] ? htmlspecialchars($tx['keterangan']) : '<span style="color:#cbd5e1">-</span>' ?>
                        </td>
                        <td><?= htmlspecialchars($tx['nama_akun'] ?? '-') ?></td>
                        <td><span class="badge <?= $tx['tipe'] ?>"><?= $tx['tipe'] ?></span></td>
                        <td
                            style="font-weight:700;color:<?= $tx['tipe']==='pemasukan'?'#16a34a':($tx['tipe']==='pengeluaran'?'#dc2626':'#7c3aed') ?>">
                            <?= ($tx['tipe']==='pemasukan'?'+':($tx['tipe']==='pengeluaran'?'-':'')) . rp($tx['jumlah']) ?>
                        </td>
                        <td>
                            <a href="#" class="action-btn btn-edit"
                                onclick="openEdit(<?= $tx['id'] ?>, '<?= htmlspecialchars($tx['keterangan'], ENT_QUOTES) ?>', '<?= $tx['tanggal'] ?>', '<?= $tx['tipe'] ?>', <?= $tx['jumlah'] ?>); return false;">
                                Edit
                            </a>
                            <?php if ($tx['tipe'] !== 'transfer'): ?>
                            <a href="?delete=<?= $tx['id'] ?>" class="action-btn btn-delete"
                                onclick="return confirm('Yakin hapus? Saldo akun akan dikembalikan.')">
                                Hapus
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6" class="empty">📭 Tidak ada transaksi ditemukan</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL EDIT -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal">
            <h2>✏️ Edit Transaksi</h2>
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

    function closeModal() {
        document.getElementById('modalOverlay').classList.remove('show');
    }
    document.getElementById('modalOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    </script>
</body>

</html>