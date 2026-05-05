<?php
if (!defined('PARTIAL_MODE')) { header('Location: index.php?tab=akun'); exit; }

$userId = getOwnerUserId();
$error = '';
$editData = null;

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $conn->prepare("DELETE FROM akun_tf WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
    $_SESSION['success'] = "Akun berhasil dihapus";
    header("Location: index.php?tab=akun"); exit;
}

if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM akun_tf WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $editData = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $namaAkun  = trim($_POST['akun'] ?? '');
    $jenisAkun = $_POST['jenis'] ?? '';
    $saldoAwal = $_POST['saldo_awal'];

    if (empty($namaAkun) || empty($jenisAkun)) {
        $error = "Semua field wajib diisi!";
    } else {
        if (isset($_POST['id']) && $_POST['id'] != '') {
            $conn->prepare("UPDATE akun_tf SET nama_akun=?, jenis_akun=?, saldo_awal=? WHERE id=? AND user_id=?")
                 ->execute([$namaAkun, $jenisAkun, $saldoAwal, $_POST['id'], $userId]);
            $_SESSION['success'] = "Akun berhasil diperbarui";
        } else {
            $conn->prepare("INSERT INTO akun_tf (user_id, nama_akun, jenis_akun, saldo_awal) VALUES (?, ?, ?, ?)")
                 ->execute([$userId, $namaAkun, $jenisAkun, $saldoAwal]);
            $_SESSION['success'] = "Akun berhasil ditambahkan";
        }
        header("Location: index.php?tab=akun"); exit;
    }
}

$stmtAkun = $conn->prepare("SELECT * FROM akun_tf WHERE user_id = ? ORDER BY nama_akun ASC");
$stmtAkun->execute([$userId]);
$akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if(isset($_SESSION['success'])): ?>
<div class="alert alert-success"><?= $_SESSION['success'] ?></div>
<?php unset($_SESSION['success']); endif; ?>
<?php if($error): ?>
<div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<div class="form-wrapper">
    <div class="card">
        <div class="card-title"><?= $editData ? 'Edit Akun' : 'Tambah Akun Baru' ?></div>
        <form method="POST" action="?tab=akun">
            <?php if($editData): ?>
            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Nama Rekening</label>
                <input type="text" name="akun" value="<?= $editData ? htmlspecialchars($editData['nama_akun']) : '' ?>" placeholder="Contoh: BCA, Kas Toko">
            </div>
            <div class="form-group">
                <label>Jenis Rekening</label>
                <select name="jenis">
                    <option value="">-- Pilih Tipe --</option>
                    <?php foreach(['kas','bank','wallet','kredit'] as $j):
                        $selected = ($editData && $editData['jenis_akun'] == $j) ? 'selected' : ''; ?>
                    <option value="<?= $j ?>" <?= $selected ?>><?= ucfirst($j) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Saldo Awal</label>
                <input type="number" name="saldo_awal" value="<?= $editData ? $editData['saldo_awal'] : 0 ?>">
            </div>
            <button type="submit" class="btn-submit"><?= $editData ? 'Update Akun' : 'Simpan Akun' ?></button>
            <?php if ($editData): ?>
            <a href="?tab=akun" class="cancel-link">Batal</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="table-card">
    <div class="table-header"><h2>Daftar Akun</h2><span class="result-count"><?= count($akunList) ?> akun</span></div>
    <table>
        <thead><tr><th>Nama Akun</th><th>Tipe</th><th>Saldo</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php if (count($akunList) > 0): foreach($akunList as $akun): ?>
            <tr>
                <td style="font-weight:600"><?= htmlspecialchars($akun['nama_akun']) ?></td>
                <td><?= strtoupper($akun['jenis_akun']) ?></td>
                <td style="font-weight:700;color:var(--accent)">Rp <?= number_format($akun['saldo_awal'],0,',','.') ?></td>
                <td>
                    <a href="?tab=akun&edit=<?= $akun['id'] ?>" class="action-btn btn-edit">Edit</a>
                    <a href="?tab=akun&delete=<?= $akun['id'] ?>" onclick="return confirm('Yakin hapus akun ini?')" class="action-btn btn-delete">Hapus</a>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" class="empty">Belum ada akun</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
