<?php
if (!defined('PARTIAL_MODE')) { header('Location: index.php?tab=riwayat'); exit; }

$userId  = getUserId();
$ownerId = getOwnerUserId();
$bizIds  = getBusinessUserIds();
$biz     = buildInClause($bizIds);

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
                    ->execute([$tx['jumlah'], $tx['akuntf_id'], $ownerId]);
            } elseif ($tx['tipe'] === 'pengeluaran') {
                $conn->prepare("UPDATE akun_tf SET saldo_awal = saldo_awal + ? WHERE id = ? AND user_id = ?")
                    ->execute([$tx['jumlah'], $tx['akuntf_id'], $ownerId]);
            }
            $conn->prepare("DELETE FROM transaksi WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
            $conn->commit();
            $_SESSION['success'] = "Transaksi berhasil dihapus & saldo dikembalikan";
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = "Gagal menghapus transaksi";
        }
    }
    header("Location: index.php?tab=riwayat");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id         = (int) $_POST['edit_id'];
    $keterangan = trim($_POST['keterangan'] ?? '');
    $tanggal    = $_POST['tanggal'] ?? '';

    if (!empty($keterangan) && !empty($tanggal)) {
        $conn->prepare("UPDATE transaksi SET keterangan = ?, tanggal = ? WHERE id = ? AND user_id = ?")
            ->execute([$keterangan, $tanggal, $id, $userId]);
        $_SESSION['success'] = "Transaksi berhasil diperbarui";
        header("Location: index.php?tab=riwayat");
        exit;
    }
}

$search = trim($_GET['search'] ?? '');
$tipe   = $_GET['tipe'] ?? '';
$bulan  = $_GET['bulan'] ?? '';

$where = "WHERE t.user_id IN ({$biz['placeholders']})";
$params = $biz['values'];

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

if (!function_exists('rp')) { function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); } }
?>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success"><?= $_SESSION['success'] ?></div>
<?php unset($_SESSION['success']); endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-error"><?= $_SESSION['error'] ?></div>
<?php unset($_SESSION['error']); endif; ?>


<div class="filter-card">
    <form method="GET" class="filter-form">
        <input type="hidden" name="tab" value="riwayat">
        <div class="filter-group">
            <label>Cari Keterangan</label>
            <input type="text" name="search" placeholder="Cari transaksi..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-group">
            <label>Tipe</label>
            <select name="tipe">
                <option value="">Semua</option>
                <option value="pemasukan" <?= $tipe === 'pemasukan' ? 'selected' : '' ?>>Pemasukan</option>
                <option value="pengeluaran" <?= $tipe === 'pengeluaran' ? 'selected' : '' ?>>Pengeluaran</option>
                <option value="transfer" <?= $tipe === 'transfer' ? 'selected' : '' ?>>Transfer</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Bulan</label>
            <input type="month" name="bulan" value="<?= htmlspecialchars($bulan) ?>">
        </div>
        <button type="submit" class="btn-filter">Filter</button>
    </form>
</div>

<div class="table-card">
    <div class="table-header"><h2>Riwayat Transaksi</h2><span class="result-count"><?= count($txList) ?> transaksi ditemukan</span></div>
    <table>
        <thead><tr><th>#</th><th>Tanggal</th><th>Tipe</th><th>Akun</th><th>Keterangan</th><th>Nominal</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php if (count($txList) > 0): $no = 1; foreach ($txList as $tx): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= date('d M Y', strtotime($tx['tanggal'])) ?></td>
                <td><span class="badge <?= $tx['tipe'] ?>"><?= $tx['tipe'] ?></span></td>
                <td><?= htmlspecialchars($tx['nama_akun'] ?? '-') ?></td>
                <td><?= htmlspecialchars($tx['keterangan'] ?? '-') ?></td>
                <td class="<?= $tx['tipe']==='pemasukan'?'green':($tx['tipe']==='pengeluaran'?'red':'') ?>" style="font-weight:700">
                    <?= ($tx['tipe']==='pemasukan'?'+':($tx['tipe']==='pengeluaran'?'-':'')) . rp($tx['jumlah']) ?>
                </td>
                <td>
                    <?php if ($tx['tipe'] !== 'transfer'): ?>
                    <button class="action-btn btn-edit" onclick="openEdit(<?= htmlspecialchars(json_encode($tx)) ?>)">Edit</button>
                    <a href="?tab=riwayat&delete=<?= $tx['id'] ?>" onclick="return confirm('Yakin hapus transaksi ini? Saldo akan dikembalikan.')" class="action-btn btn-delete">Hapus</a>
                    <?php else: ?>
                    <span style="color:var(--text-muted);font-size:12px">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" class="empty">Belum ada transaksi</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- EDIT MODAL -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:999;align-items:center;justify-content:center">
    <div class="card" style="max-width:500px;width:90%;margin:auto;margin-top:10vh">
        <div class="card-title">Edit Transaksi</div>
        <form method="POST" action="?tab=riwayat">
            <input type="hidden" name="edit_id" id="editId">
            <div class="form-group">
                <label>Keterangan</label>
                <textarea name="keterangan" id="editKeterangan" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label>Tanggal</label>
                <input type="date" name="tanggal" id="editTanggal" required>
            </div>
            <button type="submit" class="btn-submit">Simpan Perubahan</button>
            <button type="button" class="btn-submit red" style="margin-top:8px" onclick="closeEdit()">Batal</button>
        </form>
    </div>
</div>

<script>
function openEdit(tx) {
    document.getElementById('editId').value = tx.id;
    document.getElementById('editKeterangan').value = tx.keterangan || '';
    document.getElementById('editTanggal').value = tx.tanggal;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEdit() { document.getElementById('editModal').style.display = 'none'; }
</script>
