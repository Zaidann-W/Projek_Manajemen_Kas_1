<?php
if (!defined('PARTIAL_MODE')) { header('Location: index.php?tab=kategori'); exit; }

$userId = getOwnerUserId();
$error  = '';
$editData = null;

// === Daftar kategori default UMKM — edit di: config/default_kategori.php ===
include __DIR__ . '/../config/default_kategori.php';

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $conn->prepare("DELETE FROM kategori_cashflow WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
    $_SESSION['success'] = "Kategori berhasil dihapus";
    header("Location: index.php?tab=kategori"); exit;
}

// === Reset ke default: tambahkan kategori yang belum ada ===
if (isset($_GET['reset_default'])) {
    $stmtExisting = $conn->prepare("SELECT nama_kategori, kategori FROM kategori_cashflow WHERE user_id = ?");
    $stmtExisting->execute([$userId]);
    $existing = $stmtExisting->fetchAll(PDO::FETCH_ASSOC);

    // Buat lookup: "nama|tipe" → true
    $existingLookup = [];
    foreach ($existing as $e) {
        $existingLookup[strtolower($e['nama_kategori']) . '|' . $e['kategori']] = true;
    }

    $stmtInsert = $conn->prepare("INSERT INTO kategori_cashflow (user_id, kategori, nama_kategori) VALUES (?, ?, ?)");
    $added = 0;
    foreach ($defaultKategori as $k) {
        $key = strtolower($k[1]) . '|' . $k[0];
        if (!isset($existingLookup[$key])) {
            $stmtInsert->execute([$userId, $k[0], $k[1]]);
            $added++;
        }
    }

    if ($added > 0) {
        $_SESSION['success'] = "$added kategori default berhasil ditambahkan!";
    } else {
        $_SESSION['success'] = "Semua kategori default sudah ada.";
    }
    header("Location: index.php?tab=kategori"); exit;
}

if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM kategori_cashflow WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $namaKategori = trim($_POST['nama_kategori'] ?? '');
    $kategori     = $_POST['kategori'] ?? '';

    if (empty($namaKategori) || empty($kategori)) {
        $error = "Semua field wajib diisi!";
    } else {
        if (isset($_POST['id']) && $_POST['id'] != '') {
            $conn->prepare("UPDATE kategori_cashflow SET nama_kategori = ?, kategori = ? WHERE id = ? AND user_id = ?")
                 ->execute([$namaKategori, $kategori, $_POST['id'], $userId]);
            $_SESSION['success'] = "Kategori berhasil diperbarui";
        } else {
            $conn->prepare("INSERT INTO kategori_cashflow (user_id, nama_kategori, kategori) VALUES (?, ?, ?)")
                 ->execute([$userId, $namaKategori, $kategori]);
            $_SESSION['success'] = "Kategori berhasil ditambahkan";
        }
        header("Location: index.php?tab=kategori"); exit;
    }
}

$stmt = $conn->prepare("SELECT * FROM kategori_cashflow WHERE user_id = ? ORDER BY kategori, nama_kategori");
$stmt->execute([$userId]);
$kategoriList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pisahkan untuk tampilan preview default
$masukList  = array_filter($kategoriList, fn($k) => $k['kategori'] === 'masuk');
$keluarList = array_filter($kategoriList, fn($k) => $k['kategori'] === 'keluar');
?>

<?php if(isset($_SESSION['success'])): ?>
<div class="alert alert-success"><?= $_SESSION['success'] ?></div>
<?php unset($_SESSION['success']); endif; ?>
<?php if($error): ?>
<div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<div class="form-wrapper">
    <div class="card">
        <div class="card-title"><?= $editData ? 'Edit Kategori' : 'Tambah Kategori Baru' ?></div>
        <form method="POST" action="?tab=kategori">
            <?php if($editData): ?>
            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Nama Kategori</label>
                <input type="text" name="nama_kategori" placeholder="Contoh: Gaji, Belanja, Transport..."
                    value="<?= $editData ? htmlspecialchars($editData['nama_kategori']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Tipe</label>
                <div class="tipe-grid">
                    <button type="button"
                        class="tipe-btn masuk <?= (!$editData || $editData['kategori']==='masuk') ? 'active' : '' ?>"
                        onclick="setTipe('masuk')">Pemasukan</button>
                    <button type="button"
                        class="tipe-btn keluar <?= ($editData && $editData['kategori']==='keluar') ? 'active' : '' ?>"
                        onclick="setTipe('keluar')">Pengeluaran</button>
                </div>
                <input type="hidden" name="kategori" id="tipeInput"
                    value="<?= $editData ? $editData['kategori'] : 'masuk' ?>">
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn-submit" style="width:auto;flex:1"><?= $editData ? 'Update Kategori' : 'Simpan Kategori' ?></button>
                <?php if ($editData): ?>
                <a href="?tab=kategori" class="btn-submit" style="width:auto;flex:0 0 100px;background:var(--bg-card);border:1px solid var(--border);color:var(--text-secondary);text-align:center;text-decoration:none">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Daftar Kategori</h2>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <span class="result-count"><?= count($kategoriList) ?> kategori</span>
            <a href="?tab=kategori&reset_default=1"
               onclick="return confirm('Tambahkan kategori default UMKM yang belum ada?\n(Kategori yang sudah ada tidak akan digandakan)')"
               class="reset-default-btn">
                + Tambah Kategori Default
            </a>
        </div>
    </div>

    <!-- Preview daftar default (collapsible) -->
    <div class="default-preview" id="defaultPreviewWrap">
        <button type="button" class="default-preview-toggle" onclick="togglePreview()">
            <span>Lihat daftar kategori default UMKM</span>
            <span id="previewArrow">▾</span>
        </button>
        <div id="defaultPreviewContent" style="display:none">
            <div class="default-preview-grid">
                <div class="default-preview-col">
                    <div class="default-preview-title green">Pemasukan (<?= count(array_filter($defaultKategori, fn($k) => $k[0]==='masuk')) ?>)</div>
                    <?php foreach ($defaultKategori as $k): if ($k[0] !== 'masuk') continue; ?>
                    <div class="default-preview-item"><?= htmlspecialchars($k[1]) ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="default-preview-col">
                    <div class="default-preview-title red">Pengeluaran (<?= count(array_filter($defaultKategori, fn($k) => $k[0]==='keluar')) ?>)</div>
                    <?php foreach ($defaultKategori as $k): if ($k[0] !== 'keluar') continue; ?>
                    <div class="default-preview-item"><?= htmlspecialchars($k[1]) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <table>
        <thead><tr><th>#</th><th>Nama Kategori</th><th>Tipe</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php if (count($kategoriList) > 0): $no = 1; foreach ($kategoriList as $k): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($k['nama_kategori']) ?></td>
                <td><span class="badge <?= $k['kategori'] ?>"><?= $k['kategori'] === 'masuk' ? 'Pemasukan' : 'Pengeluaran' ?></span></td>
                <td>
                    <a href="?tab=kategori&edit=<?= $k['id'] ?>" class="action-btn btn-edit">Edit</a>
                    <a href="?tab=kategori&delete=<?= $k['id'] ?>" onclick="return confirm('Yakin hapus kategori ini?')" class="action-btn btn-delete">Hapus</a>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" class="empty">Belum ada kategori — klik "Tambah Kategori Default" untuk mulai</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.reset-default-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    border-radius: var(--radius-xs);
    font-size: 12px;
    font-weight: 600;
    background: var(--accent-glow);
    color: var(--accent);
    border: 1px solid rgba(74,108,247,.25);
    text-decoration: none;
    transition: var(--transition);
    white-space: nowrap;
}
.reset-default-btn:hover {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
}

.default-preview {
    border-bottom: 1px solid var(--border);
}
.default-preview-toggle {
    width: 100%;
    padding: 11px 22px;
    background: none;
    border: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted);
    cursor: pointer;
    font-family: var(--font);
    transition: var(--transition);
}
.default-preview-toggle:hover {
    color: var(--text-primary);
    background: var(--bg-card-hover);
}
.default-preview-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    padding: 14px 22px 18px;
    background: var(--bg-body);
    border-top: 1px solid var(--border);
}
.default-preview-col { padding-right: 16px; }
.default-preview-col:last-child { padding-right: 0; border-left: 1px solid var(--border); padding-left: 16px; }
.default-preview-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: 8px;
}
.default-preview-title.green { color: var(--green); }
.default-preview-title.red   { color: var(--red); }
.default-preview-item {
    font-size: 13px;
    color: var(--text-secondary);
    padding: 3px 0;
    border-bottom: 1px solid var(--border-light);
}
.default-preview-item:last-child { border-bottom: none; }

@media (max-width: 600px) {
    .default-preview-grid { grid-template-columns: 1fr; }
    .default-preview-col:last-child { border-left: none; padding-left: 0; border-top: 1px solid var(--border); padding-top: 14px; margin-top: 14px; }
}
</style>

<script>
function setTipe(val) {
    document.getElementById('tipeInput').value = val;
    document.querySelectorAll('.tipe-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('.tipe-btn.' + val).classList.add('active');
}
function togglePreview() {
    var content = document.getElementById('defaultPreviewContent');
    var arrow   = document.getElementById('previewArrow');
    var visible = content.style.display !== 'none';
    content.style.display = visible ? 'none' : 'block';
    arrow.textContent = visible ? '▾' : '▴';
}
</script>
