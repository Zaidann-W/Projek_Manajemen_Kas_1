<?php
if (!defined('PARTIAL_MODE')) { header('Location: index.php?tab=kelola'); exit; }

$userId = getOwnerUserId();
$bizIds = getBusinessUserIds();
$biz    = buildInClause($bizIds);
$error  = '';
$editData = null;

$stmtKat = $conn->prepare("SELECT id, nama_kategori FROM kategori_cashflow WHERE user_id = ? AND kategori = 'keluar' ORDER BY nama_kategori");
$stmtKat->execute([$userId]);
$kategoriList = $stmtKat->fetchAll(PDO::FETCH_ASSOC);

// === Ambil data rata-rata pengeluaran per kategori (3 bulan terakhir) ===
$katSpending = [];
if (count($kategoriList) > 0) {
    $stmtAvg = $conn->prepare("
        SELECT kategoricf_id,
               ROUND(AVG(monthly_total)) AS avg_spending,
               ROUND(MAX(monthly_total)) AS max_spending,
               COUNT(*) AS bulan_count
        FROM (
            SELECT kategoricf_id,
                   SUM(jumlah) AS monthly_total
            FROM transaksi
            WHERE user_id IN ({$biz['placeholders']})
              AND tipe = 'pengeluaran'
              AND kategoricf_id IS NOT NULL
            GROUP BY kategoricf_id, YEAR(tanggal), MONTH(tanggal)
        ) AS monthly
        GROUP BY kategoricf_id
    ");
    $stmtAvg->execute($biz['values']);
    foreach ($stmtAvg->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $katSpending[$row['kategoricf_id']] = [
            'avg' => (float)$row['avg_spending'],
            'max' => (float)$row['max_spending'],
            'months' => (int)$row['bulan_count']
        ];
    }
}

// === Ambil pengeluaran terakhir per kategori (untuk info) ===
$katLastTx = [];
if (count($kategoriList) > 0) {
    $stmtLast = $conn->prepare("
        SELECT t.kategoricf_id, t.jumlah, t.keterangan, t.tanggal
        FROM transaksi t
        INNER JOIN (
            SELECT kategoricf_id, MAX(id) AS last_id
            FROM transaksi
            WHERE user_id IN ({$biz['placeholders']})
              AND tipe = 'pengeluaran'
              AND kategoricf_id IS NOT NULL
            GROUP BY kategoricf_id
        ) latest ON t.id = latest.last_id
    ");
    $stmtLast->execute($biz['values']);
    foreach ($stmtLast->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $katLastTx[$row['kategoricf_id']] = [
            'jumlah' => (float)$row['jumlah'],
            'keterangan' => $row['keterangan'],
            'tanggal' => $row['tanggal']
        ];
    }
}

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
        <form method="POST" action="?tab=kelola" id="budgetForm">
            <?php if ($editData): ?>
            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Kategori Pengeluaran</label>
                <select name="kategoricf_id" id="kategoriSelect" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($kategoriList as $kat): ?>
                    <option value="<?= $kat['id'] ?>" <?= ($editData && $editData['kategoricf_id'] == $kat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                    <?php if (count($kategoriList) === 0): ?>
                    <option disabled>Belum ada kategori pengeluaran - tambah di menu Kategori</option>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Info pengeluaran sebelumnya -->
            <div id="spendingInfo" class="spending-info" style="display:none">
                <div class="spending-info-inner">
                    <div class="spending-info-row">
                        <span class="spending-label" id="avgLabel">Rata-rata / bulan</span>
                        <span class="spending-value" id="avgSpending">-</span>
                    </div>
                    <div class="spending-info-row">
                        <span class="spending-label">Tertinggi / bulan</span>
                        <span class="spending-value" id="maxSpending">-</span>
                    </div>
                    <div class="spending-info-row">
                        <span class="spending-label">Terakhir bayar</span>
                        <span class="spending-value" id="lastSpending">-</span>
                    </div>
                </div>
                <div class="spending-suggestion" id="suggestionArea">
                    <span class="spending-suggestion-label">Saran budget:</span>
                    <div class="suggestion-chips" id="suggestionChips"></div>
                </div>
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
                <input type="number" name="jumlah_budget" id="jumlahBudget" placeholder="Masukkan nominal budget" min="1" value="<?= $editData ? $editData['jumlah_budget'] : '' ?>" required>
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

<style>
.spending-info {
    background: var(--bg-body);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 14px 16px;
    margin-bottom: 16px;
    animation: slideDown .25s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}
.spending-info-inner {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 12px;
}
.spending-info-row {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.spending-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .3px;
}
.spending-value {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-heading);
}
.spending-suggestion {
    border-top: 1px solid var(--border);
    padding-top: 10px;
}
.spending-suggestion-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 8px;
    display: block;
}
.suggestion-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.suggestion-chip {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid var(--border);
    background: var(--bg-card);
    color: var(--text-primary);
    transition: all .2s ease;
    font-family: var(--font);
}
.suggestion-chip:hover {
    border-color: var(--amber);
    background: var(--amber-bg);
    color: var(--amber);
}
.suggestion-chip.active {
    border-color: var(--amber);
    background: var(--amber-bg);
    color: var(--amber);
}
.spending-no-data {
    font-size: 13px;
    color: var(--text-muted);
    font-style: italic;
}

@media (max-width: 600px) {
    .spending-info-inner {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .spending-info-row {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    .suggestion-chips {
        gap: 6px;
    }
    .suggestion-chip {
        padding: 5px 12px;
        font-size: 12px;
    }
}
</style>

<script>
(function() {
    var spendingData = <?= json_encode($katSpending) ?>;
    var lastTxData = <?= json_encode($katLastTx) ?>;
    var select = document.getElementById('kategoriSelect');
    var infoBox = document.getElementById('spendingInfo');
    var avgEl = document.getElementById('avgSpending');
    var avgLabel = document.getElementById('avgLabel');
    var maxEl = document.getElementById('maxSpending');
    var lastEl = document.getElementById('lastSpending');
    var chipsEl = document.getElementById('suggestionChips');
    var budgetInput = document.getElementById('jumlahBudget');
    var isEdit = <?= $editData ? 'true' : 'false' ?>;

    function formatRp(n) {
        return 'Rp ' + Math.round(n).toLocaleString('id-ID');
    }

    function roundUp(n, to) {
        return Math.ceil(n / to) * to;
    }

    function updateInfo() {
        var katId = select.value;
        if (!katId) {
            infoBox.style.display = 'none';
            return;
        }

        var data = spendingData[katId];
        var lastTx = lastTxData[katId];

        if (!data && !lastTx) {
            // Belum ada data pengeluaran untuk kategori ini
            avgEl.textContent = '-';
            maxEl.textContent = '-';
            lastEl.textContent = '-';
            chipsEl.innerHTML = '<span class="spending-no-data">Belum ada riwayat — isi manual</span>';
            infoBox.style.display = 'block';
            return;
        }

        infoBox.style.display = 'block';

        // Tampilkan info
        if (data) {
            avgLabel.textContent = 'Rata-rata / bulan' + (data.months > 1 ? ' (' + data.months + ' bln)' : '');
            avgEl.textContent = formatRp(data.avg);
            maxEl.textContent = formatRp(data.max);
        } else {
            avgLabel.textContent = 'Rata-rata / bulan';
            avgEl.textContent = '-';
            maxEl.textContent = '-';
        }

        if (lastTx) {
            var tgl = new Date(lastTx.tanggal);
            var tglStr = tgl.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
            lastEl.textContent = formatRp(lastTx.jumlah) + ' (' + tglStr + ')';
        } else {
            lastEl.textContent = '-';
        }

        // Generate suggestion chips
        var suggestions = [];
        if (data) {
            // Saran 1: Rata-rata (dibulatkan ke 50rb terdekat)
            var avgRound = roundUp(data.avg, 50000);
            suggestions.push({ label: 'Rata-rata', value: avgRound });

            // Saran 2: Rata-rata + 20% buffer
            var buffer = roundUp(data.avg * 1.2, 50000);
            if (buffer !== avgRound) {
                suggestions.push({ label: '+20% buffer', value: buffer });
            }

            // Saran 3: Max spending (dibulatkan)
            var maxRound = roundUp(data.max, 50000);
            if (maxRound !== avgRound && maxRound !== buffer) {
                suggestions.push({ label: 'Tertinggi', value: maxRound });
            }
        } else if (lastTx) {
            // Kalau cuma ada 1 transaksi terakhir
            var lastRound = roundUp(lastTx.jumlah, 50000);
            suggestions.push({ label: 'Sama dgn terakhir', value: lastRound });
            var lastBuffer = roundUp(lastTx.jumlah * 1.2, 50000);
            if (lastBuffer !== lastRound) {
                suggestions.push({ label: '+20% buffer', value: lastBuffer });
            }
        }

        chipsEl.innerHTML = '';
        suggestions.forEach(function(s) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'suggestion-chip';
            chip.textContent = s.label + ': ' + formatRp(s.value);
            chip.setAttribute('data-value', s.value);
            chip.addEventListener('click', function() {
                budgetInput.value = s.value;
                // Highlight active
                chipsEl.querySelectorAll('.suggestion-chip').forEach(function(c) { c.classList.remove('active'); });
                chip.classList.add('active');
                budgetInput.focus();
            });
            chipsEl.appendChild(chip);
        });

        if (suggestions.length === 0) {
            chipsEl.innerHTML = '<span class="spending-no-data">Belum cukup data — isi manual</span>';
        }

        // Auto-fill budget kalau belum diisi dan bukan edit mode
        if (!isEdit && !budgetInput.value && data) {
            budgetInput.value = roundUp(data.avg, 50000);
            // Highlight chip yang match
            var firstChip = chipsEl.querySelector('.suggestion-chip');
            if (firstChip) firstChip.classList.add('active');
        }
    }

    select.addEventListener('change', updateInfo);

    // Ketika user manual input, un-highlight chips
    budgetInput.addEventListener('input', function() {
        var val = parseInt(budgetInput.value);
        chipsEl.querySelectorAll('.suggestion-chip').forEach(function(c) {
            if (parseInt(c.getAttribute('data-value')) === val) {
                c.classList.add('active');
            } else {
                c.classList.remove('active');
            }
        });
    });

    // Trigger on page load (e.g. edit mode with pre-selected category)
    if (select.value) {
        updateInfo();
    }
})();
</script>
