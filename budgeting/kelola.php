<?php
if (!defined('PARTIAL_MODE')) { header('Location: index.php?tab=kelola'); exit; }

$userId = getOwnerUserId();
$error  = '';
$editData = null;

$stmtKat = $conn->prepare("SELECT id, nama_kategori FROM kategori_cashflow WHERE user_id = ? AND kategori = 'keluar' ORDER BY nama_kategori");
$stmtKat->execute([$userId]);
$kategoriList = $stmtKat->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $conn->prepare("DELETE FROM budget WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
    $_SESSION['success'] = "Budget berhasil dihapus";
    header("Location: index.php?tab=kelola"); exit;
}

if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM budget WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kategoriId   = $_POST['kategoricf_id'] ?? '';
    $bulan        = (int) ($_POST['bulan'] ?? 0);
    $tahun        = (int) ($_POST['tahun'] ?? 0);
    $jumlahBudget = $_POST['jumlah_budget'] ?? '';

    if (empty($kategoriId) || $bulan < 1 || $bulan > 12 || $tahun < 2020 || empty($jumlahBudget)) {
        $error = "Semua field wajib diisi dengan benar!";
    } elseif ($jumlahBudget <= 0) {
        $error = "Jumlah budget harus lebih dari 0!";
    } else {
        if (isset($_POST['id']) && $_POST['id'] != '') {
            $conn->prepare("UPDATE budget SET kategoricf_id = ?, bulan = ?, tahun = ?, jumlah_budget = ? WHERE id = ? AND user_id = ?")
                 ->execute([$kategoriId, $bulan, $tahun, $jumlahBudget, $_POST['id'], $userId]);
            $_SESSION['success'] = "Budget berhasil diperbarui";
        } else {
            $cek = $conn->prepare("SELECT id, jumlah_budget FROM budget WHERE user_id = ? AND kategoricf_id = ? AND bulan = ? AND tahun = ?");
            $cek->execute([$userId, $kategoriId, $bulan, $tahun]);
            $existing = $cek->fetch();
            if ($existing) {
                // Sudah ada → tambahkan nominal ke budget yang existing
                $budgetBaru = $existing['jumlah_budget'] + $jumlahBudget;
                $conn->prepare("UPDATE budget SET jumlah_budget = ? WHERE id = ? AND user_id = ?")
                     ->execute([$budgetBaru, $existing['id'], $userId]);
                $_SESSION['success'] = "Budget berhasil ditambah! Total budget sekarang: Rp " . number_format($budgetBaru, 0, ',', '.');
            } else {
                $conn->prepare("INSERT INTO budget (user_id, kategoricf_id, bulan, tahun, jumlah_budget) VALUES (?, ?, ?, ?, ?)")
                     ->execute([$userId, $kategoriId, $bulan, $tahun, $jumlahBudget]);
                $_SESSION['success'] = "Budget berhasil ditambahkan";
            }
        }
        if (empty($error)) { header("Location: index.php?tab=kelola"); exit; }
    }
}

$filterBulan = isset($_GET['fb']) ? (int) $_GET['fb'] : (int) date('m');
$filterTahun = isset($_GET['ft']) ? (int) $_GET['ft'] : (int) date('Y');

$stmtBudget = $conn->prepare("SELECT b.*, k.nama_kategori FROM budget b LEFT JOIN kategori_cashflow k ON b.kategoricf_id = k.id WHERE b.user_id = ? AND b.bulan = ? AND b.tahun = ? ORDER BY k.nama_kategori");
$stmtBudget->execute([$userId, $filterBulan, $filterTahun]);
$budgetList = $stmtBudget->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('rp')) { function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); } }
$namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success"><?= $_SESSION['success'] ?></div>
<?php unset($_SESSION['success']); endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="form-wrapper">
    <div class="card">
        <div class="card-title"><?= $editData ? 'Edit Budget' : 'Tambah Budget Baru' ?></div>
        <form method="POST" action="?tab=kelola">
            <?php if ($editData): ?>
            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Kategori Pengeluaran</label>
                <select name="kategoricf_id" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($kategoriList as $kat): ?>
                    <option value="<?= $kat['id'] ?>" <?= ($editData && $editData['kategoricf_id'] == $kat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                    <?php if (count($kategoriList) === 0): ?>
                    <option disabled>Belum ada kategori pengeluaran - tambah di menu Kategori</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Bulan</label>
                    <select name="bulan" required>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= ($editData ? $editData['bulan'] : date('m')) == $i ? 'selected' : '' ?>><?= $namaBulan[$i] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tahun</label>
                    <select name="tahun" required>
                        <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                        <option value="<?= $y ?>" <?= ($editData ? $editData['tahun'] : date('Y')) == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Jumlah Budget (Rp)</label>
                <input type="number" name="jumlah_budget" placeholder="Masukkan nominal budget" min="1" value="<?= $editData ? $editData['jumlah_budget'] : '' ?>" required>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn-submit amber" style="width:auto;flex:1"><?= $editData ? 'Update Budget' : 'Simpan Budget' ?></button>
                <?php if ($editData): ?>
                <a href="?tab=kelola" class="btn-submit" style="width:auto;flex:0 0 100px;background:var(--bg-card);border:1px solid var(--border);color:var(--text-secondary);text-align:center;text-decoration:none">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="filter-card">
    <form method="GET" class="filter-form">
        <input type="hidden" name="tab" value="kelola">
        <div class="filter-group">
            <label>Bulan</label>
            <select name="fb">
                <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?= $i ?>" <?= $filterBulan == $i ? 'selected' : '' ?>><?= $namaBulan[$i] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Tahun</label>
            <select name="ft">
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                <option value="<?= $y ?>" <?= $filterTahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="btn-filter">Tampilkan</button>
    </form>
</div>

<div class="table-card">
    <div class="table-header"><h2>Budget <?= $namaBulan[$filterBulan] ?> <?= $filterTahun ?></h2><span class="result-count"><?= count($budgetList) ?> budget ditemukan</span></div>
    <table>
        <thead><tr><th>#</th><th>Kategori</th><th>Jumlah Budget</th><th>Dibuat</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php if (count($budgetList) > 0): $no = 1; foreach ($budgetList as $b): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($b['nama_kategori'] ?? 'Kategori dihapus') ?></td>
                <td style="font-weight:700;color:var(--amber)"><?= rp($b['jumlah_budget']) ?></td>
                <td style="color:var(--text-muted);font-size:13px"><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                <td>
                    <a href="?tab=kelola&edit=<?= $b['id'] ?>&fb=<?= $filterBulan ?>&ft=<?= $filterTahun ?>" class="action-btn btn-edit">Edit</a>
                    <a href="?tab=kelola&delete=<?= $b['id'] ?>&fb=<?= $filterBulan ?>&ft=<?= $filterTahun ?>" onclick="return confirm('Yakin hapus budget ini?')" class="action-btn btn-delete">Hapus</a>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="empty">Belum ada budget untuk bulan ini</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
