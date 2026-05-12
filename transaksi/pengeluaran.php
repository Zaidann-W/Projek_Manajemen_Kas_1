<?php
if (!defined('PARTIAL_MODE')) { header('Location: index.php?tab=pengeluaran'); exit; }

if (!function_exists('rp')) { function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); } }

$userId  = getUserId();
$ownerId = getOwnerUserId();
$bizIds  = getBusinessUserIds();
$biz     = buildInClause($bizIds);
$error = '';
$success = '';

$stmtAkun = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY nama_akun");
$stmtAkun->execute([$ownerId]);
$akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);

$stmtKat = $conn->prepare("SELECT id, nama_kategori FROM kategori_cashflow WHERE user_id = ? AND kategori = 'keluar' ORDER BY nama_kategori");
$stmtKat->execute([$ownerId]);
$kategoriList = $stmtKat->fetchAll(PDO::FETCH_ASSOC);

// === Ambil budget & realisasi bulan ini per kategori ===
$budgetInfo = [];
if (count($kategoriList) > 0) {
    $stmtBudget = $conn->prepare("
        SELECT b.kategoricf_id,
               b.jumlah_budget,
               COALESCE(SUM(t.jumlah), 0) AS terpakai
        FROM budget b
        LEFT JOIN transaksi t ON t.kategoricf_id = b.kategoricf_id
            AND t.user_id IN ({$biz['placeholders']})
            AND t.tipe = 'pengeluaran'
            AND MONTH(t.tanggal) = MONTH(CURDATE())
            AND YEAR(t.tanggal) = YEAR(CURDATE())
        WHERE b.user_id = ?
          AND b.bulan = MONTH(CURDATE())
          AND b.tahun = YEAR(CURDATE())
        GROUP BY b.id, b.kategoricf_id, b.jumlah_budget
    ");
    $stmtBudget->execute(array_merge($biz['values'], [$ownerId]));
    foreach ($stmtBudget->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sisa = $row['jumlah_budget'] - $row['terpakai'];
        $persen = $row['jumlah_budget'] > 0 ? round(($row['terpakai'] / $row['jumlah_budget']) * 100, 1) : 0;
        $budgetInfo[$row['kategoricf_id']] = [
            'budget'   => (float)$row['jumlah_budget'],
            'terpakai' => (float)$row['terpakai'],
            'sisa'     => (float)$sisa,
            'persen'   => $persen,
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rekening   = trim($_POST['rekening'] ?? '');
    $jumlah     = $_POST['jumlah'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    $tanggal    = $_POST['tanggal'] ?? '';
    $kategoriId = $_POST['kategori_id'] ?? null;

    if (empty($rekening) || empty($jumlah) || empty($tanggal) || empty($keterangan)) {
        $error = "Semua field wajib diisi!";
    } elseif ($jumlah <= 0) {
        $error = "Jumlah harus lebih dari 0!";
    } else {
        try {
            $conn->beginTransaction();
            $stmtCek = $conn->prepare("SELECT id, saldo_awal FROM akun_tf WHERE id = ? AND user_id = ?");
            $stmtCek->execute([$rekening, $ownerId]);
            $akun = $stmtCek->fetch(PDO::FETCH_ASSOC);
            if (!$akun) throw new Exception('Akun tidak ditemukan');

            if ($akun['saldo_awal'] < $jumlah) {
                $error = "Saldo tidak mencukupi! Saldo akun: Rp " . number_format($akun['saldo_awal'], 0, ',', '.');
                $conn->rollBack();
            } else {
                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, akuntf_id, kategoricf_id, tipe, jumlah, keterangan, tanggal) VALUES (?, ?, ?, 'pengeluaran', ?, ?, ?)");
                $stmt->execute([$userId, $rekening, $kategoriId ?: null, $jumlah, $keterangan, $tanggal]);
                $conn->prepare("UPDATE akun_tf SET saldo_awal = saldo_awal - ? WHERE id = ? AND user_id = ?")->execute([$jumlah, $rekening, $ownerId]);
                $conn->commit();
                $success = "Pengeluaran berhasil disimpan!";

                $stmtAkun = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY nama_akun");
                $stmtAkun->execute([$ownerId]);
                $akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Terjadi kesalahan sistem";
        }
    }
}
?>
<div class="form-wrapper">
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-title">Detail Pengeluaran</div>
        <form method="POST" action="?tab=pengeluaran">
            <div class="form-group">
                <label>Pilih Rekening</label>
                <select name="rekening" id="rekeningSelect" required>
                    <option value="">-- Pilih Rekening --</option>
                    <?php foreach ($akunList as $akun): ?>
                    <option value="<?= $akun['id'] ?>" data-saldo="<?= $akun['saldo_awal'] ?>"><?= htmlspecialchars($akun['nama_akun']) ?> (<?= rp($akun['saldo_awal']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Kategori <span style="color:var(--text-muted);font-weight:400">(Opsional)</span></label>
                <select name="kategori_id" id="kategoriSelect">
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($kategoriList as $kat): ?>
                    <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Info Budget Kategori -->
            <div id="budgetInfoBox" style="display:none; margin-bottom:16px">
                <div class="budget-info-card">
                    <div class="budget-info-header">
                        <span class="budget-info-title">Budget Bulan Ini</span>
                        <span class="budget-info-month"><?= date('F Y') ?></span>
                    </div>
                    <div class="budget-info-body">
                        <div class="budget-info-row">
                            <span class="bi-label">Total Budget</span>
                            <span class="bi-value" id="biBudget">-</span>
                        </div>
                        <div class="budget-info-row">
                            <span class="bi-label">Sudah terpakai</span>
                            <span class="bi-value red" id="biTerpakai">-</span>
                        </div>
                        <div class="budget-info-row">
                            <span class="bi-label">Sisa budget</span>
                            <span class="bi-value" id="biSisa">-</span>
                        </div>
                    </div>
                    <div class="budget-info-bar-wrap">
                        <div class="budget-info-bar">
                            <div class="budget-info-bar-fill" id="biBarFill"></div>
                        </div>
                        <span class="budget-info-persen" id="biPersen">0%</span>
                    </div>
                    <div id="biWarning" class="budget-info-warning" style="display:none"></div>
                </div>
            </div>

            <div id="noBudgetInfo" style="display:none; margin-bottom:16px">
                <div class="budget-info-card no-budget">
                    <span class="no-budget-text">Belum ada budget untuk kategori ini bulan ini.</span>
                    <a href="../budgeting/index.php?tab=kelola" class="no-budget-link">Atur budget →</a>
                </div>
            </div>

            <div class="form-group">
                <label>Jumlah (Rp)</label>
                <input type="number" name="jumlah" id="jumlahInput" placeholder="Masukkan nominal" min="1" required>
            </div>
            <div class="form-group">
                <label>Tanggal</label>
                <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Keterangan</label>
                <textarea name="keterangan" placeholder="Contoh: Beli bahan baku, bayar listrik..." rows="3" required></textarea>
            </div>
            <button type="submit" class="btn-submit red">Simpan Pengeluaran</button>
        </form>
    </div>
</div>

<style>
.budget-info-card {
    background: var(--bg-body);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 14px 16px;
    animation: slideDown .2s ease;
}
@keyframes slideDown {
    from { opacity:0; transform:translateY(-6px); }
    to { opacity:1; transform:translateY(0); }
}
.budget-info-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.budget-info-title {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: .4px;
}
.budget-info-month {
    font-size: 11px;
    color: var(--text-muted);
}
.budget-info-body {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 10px;
}
.budget-info-row {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.bi-label {
    font-size: 10px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .3px;
}
.bi-value {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-heading);
}
.bi-value.red  { color: var(--red); }
.bi-value.green { color: var(--green); }
.bi-value.amber { color: var(--amber); }
.budget-info-bar-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 0;
}
.budget-info-bar {
    flex: 1;
    height: 8px;
    background: var(--border);
    border-radius: 8px;
    overflow: hidden;
}
.budget-info-bar-fill {
    height: 100%;
    border-radius: 8px;
    transition: width .4s ease, background .3s;
}
.budget-info-persen {
    font-size: 12px;
    font-weight: 700;
    min-width: 36px;
    text-align: right;
    color: var(--text-secondary);
}
.budget-info-warning {
    margin-top: 10px;
    padding: 8px 12px;
    border-radius: var(--radius-xs);
    font-size: 12px;
    font-weight: 600;
}
.budget-info-warning.warn {
    background: var(--amber-bg);
    color: var(--amber);
    border: 1px solid var(--amber-border);
}
.budget-info-warning.danger {
    background: var(--red-bg);
    color: var(--red);
    border: 1px solid var(--red-border);
}
.budget-info-card.no-budget {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}
.no-budget-text {
    font-size: 13px;
    color: var(--text-muted);
}
.no-budget-link {
    font-size: 13px;
    font-weight: 600;
    color: var(--accent);
    white-space: nowrap;
}

@media (max-width: 600px) {
    .budget-info-body { grid-template-columns: 1fr; gap: 6px; }
    .budget-info-row { flex-direction: row; justify-content: space-between; align-items: center; }
}
</style>

<script>
(function() {
    var budgetData  = <?= json_encode($budgetInfo) ?>;
    var katSelect   = document.getElementById('kategoriSelect');
    var budgetBox   = document.getElementById('budgetInfoBox');
    var noBudgetBox = document.getElementById('noBudgetInfo');
    var biBudget    = document.getElementById('biBudget');
    var biTerpakai  = document.getElementById('biTerpakai');
    var biSisa      = document.getElementById('biSisa');
    var biBarFill   = document.getElementById('biBarFill');
    var biPersen    = document.getElementById('biPersen');
    var biWarning   = document.getElementById('biWarning');
    var jumlahInput = document.getElementById('jumlahInput');

    function formatRp(n) {
        return 'Rp ' + Math.round(n).toLocaleString('id-ID');
    }

    function updateBudgetInfo() {
        var katId = katSelect.value;
        budgetBox.style.display = 'none';
        noBudgetBox.style.display = 'none';

        if (!katId) return;

        var d = budgetData[katId];
        if (!d) {
            // Kategori punya budget? Tidak ada → tampil "Belum ada budget"
            noBudgetBox.style.display = 'block';
            return;
        }

        budgetBox.style.display = 'block';

        // Hitung sisa realtime kalau user sudah isi jumlah
        var inputJumlah = parseFloat(jumlahInput.value) || 0;
        var terpakai    = d.terpakai + inputJumlah;
        var sisa        = d.budget - terpakai;
        var persen      = d.budget > 0 ? Math.min((terpakai / d.budget) * 100, 100) : 0;

        biBudget.textContent  = formatRp(d.budget);
        biTerpakai.textContent = formatRp(terpakai);

        // Warna sisa
        biSisa.className = 'bi-value';
        if (sisa < 0) {
            biSisa.textContent = '− ' + formatRp(Math.abs(sisa));
            biSisa.classList.add('red');
        } else if (sisa < d.budget * 0.2) {
            biSisa.textContent = formatRp(sisa);
            biSisa.classList.add('amber');
        } else {
            biSisa.textContent = formatRp(sisa);
            biSisa.classList.add('green');
        }

        // Progress bar warna
        var barColor;
        if (persen >= 100)      barColor = 'linear-gradient(90deg, #f87171, #dc2626)';
        else if (persen >= 80)  barColor = 'linear-gradient(90deg, #fbbf24, #f59e0b)';
        else                    barColor = 'linear-gradient(90deg, #34d399, #16a34a)';

        biBarFill.style.width      = persen + '%';
        biBarFill.style.background = barColor;
        biPersen.textContent       = Math.round(persen) + '%';
        biPersen.style.color       = persen >= 100 ? 'var(--red)' : persen >= 80 ? 'var(--amber)' : 'var(--text-secondary)';

        // Warning
        if (sisa < 0) {
            biWarning.style.display = 'block';
            biWarning.className     = 'budget-info-warning danger';
            biWarning.textContent   = 'Pengeluaran ini akan melebihi budget sebesar ' + formatRp(Math.abs(sisa)) + '!';
        } else if (persen >= 80) {
            biWarning.style.display = 'block';
            biWarning.className     = 'budget-info-warning warn';
            biWarning.textContent   = 'Budget hampir habis! Sisa ' + Math.round(100 - persen) + '% lagi.';
        } else {
            biWarning.style.display = 'none';
        }
    }

    katSelect.addEventListener('change', updateBudgetInfo);
    jumlahInput.addEventListener('input', updateBudgetInfo);
})();
</script>
