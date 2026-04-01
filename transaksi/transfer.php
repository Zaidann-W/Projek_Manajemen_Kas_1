<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$userId  = $_SESSION['user_id'];
$error   = '';
$success = '';

$stmtAkun = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY nama_akun");
$stmtAkun->execute([$userId]);
$akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dariAkun   = trim($_POST['dari_akun'] ?? '');
    $keAkun     = trim($_POST['ke_akun'] ?? '');
    $jumlah     = $_POST['jumlah'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    $tanggal    = $_POST['tanggal'] ?? '';

    if (empty($dariAkun) || empty($keAkun) || empty($jumlah) || empty($tanggal)) {
        $error = "Semua field wajib diisi!";
    } elseif ($jumlah <= 0) {
        $error = "Jumlah harus lebih dari 0!";
    } elseif ($dariAkun === $keAkun) {
        $error = "Akun asal dan tujuan tidak boleh sama!";
    } else {
        try {
            $conn->beginTransaction();
            $stmtCek = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE id = ? AND user_id = ?");
            $stmtCek->execute([$dariAkun, $userId]);
            $akunAsal = $stmtCek->fetch(PDO::FETCH_ASSOC);

            if (!$akunAsal) throw new Exception('Akun asal tidak ditemukan');

            if ($akunAsal['saldo_awal'] < $jumlah) {
                $error = "Saldo tidak mencukupi! Saldo " . htmlspecialchars($akunAsal['nama_akun']) . ": Rp " . number_format($akunAsal['saldo_awal'], 0, ',', '.');
                $conn->rollBack();
            } else {
                $stmtTujuan = $conn->prepare("SELECT id FROM akun_tf WHERE id = ? AND user_id = ?");
                $stmtTujuan->execute([$keAkun, $userId]);
                if (!$stmtTujuan->fetch()) throw new Exception('Akun tujuan tidak ditemukan');

                $conn->prepare("INSERT INTO transfer (user_id, dari_akuntf, ke_akuntf, jumlah, tanggal, keterangan) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$userId, $dariAkun, $keAkun, $jumlah, $tanggal, $keterangan]);

                $conn->prepare("INSERT INTO transaksi (user_id, akuntf_id, tipe, jumlah, keterangan, tanggal) VALUES (?, ?, 'transfer', ?, ?, ?)")
                    ->execute([$userId, $dariAkun, $jumlah, $keterangan, $tanggal]);

                $conn->prepare("UPDATE akun_tf SET saldo_awal = saldo_awal - ? WHERE id = ? AND user_id = ?")
                    ->execute([$jumlah, $dariAkun, $userId]);

                $conn->prepare("UPDATE akun_tf SET saldo_awal = saldo_awal + ? WHERE id = ? AND user_id = ?")
                    ->execute([$jumlah, $keAkun, $userId]);

                $conn->commit();
                $success = "Transfer berhasil disimpan!";

                $stmtAkun = $conn->prepare("SELECT id, nama_akun, saldo_awal FROM akun_tf WHERE user_id = ? ORDER BY nama_akun");
                $stmtAkun->execute([$userId]);
                $akunList = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Transfer Antar Akun</title>
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
        margin-bottom: 28px
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

    .form-wrapper {
        max-width: 620px
    }

    .card {
        background: #fff;
        padding: 28px;
        border-radius: 16px;
        box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
        margin-bottom: 20px
    }

    .card-title {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 1px solid #f1f5f9
    }

    .preview-box {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 22px
    }

    .preview-akun {
        flex: 1;
        background: #f5f3ff;
        border: 1.5px solid #ddd6fe;
        border-radius: 12px;
        padding: 14px;
        text-align: center;
        font-size: 13px;
        font-weight: 600;
        color: #5b21b6;
        min-height: 52px;
        display: flex;
        align-items: center;
        justify-content: center
    }

    .preview-arrow {
        font-size: 24px;
        color: #7c3aed
    }

    .form-group {
        margin-bottom: 18px
    }

    label {
        font-size: 13px;
        font-weight: 600;
        color: #475569;
        display: block;
        margin-bottom: 7px
    }

    input,
    select,
    textarea {
        width: 100%;
        padding: 11px 14px;
        border-radius: 10px;
        border: 1.5px solid #e2e8f0;
        font-size: 14px;
        color: #1e293b;
        transition: .2s;
        background: #fff
    }

    input:focus,
    select:focus,
    textarea:focus {
        outline: none;
        border-color: #7c3aed;
        box-shadow: 0 0 0 3px rgba(124, 58, 237, .1)
    }

    textarea {
        resize: none;
        height: 85px
    }

    .saldo-info {
        background: #f5f3ff;
        border: 1px solid #ddd6fe;
        border-radius: 8px;
        padding: 9px 14px;
        font-size: 13px;
        color: #5b21b6;
        margin-top: 8px;
        display: none
    }

    .saldo-info.show {
        display: block
    }

    .btn-submit {
        width: 100%;
        padding: 13px;
        background: #7c3aed;
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: .2s;
        margin-top: 4px
    }

    .btn-submit:hover {
        background: #6d28d9
    }

    .note {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 10px;
        text-align: center
    }

    .alert {
        padding: 13px 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px
    }

    .alert-error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca
    }

    .alert-success {
        background: #f5f3ff;
        color: #5b21b6;
        border: 1px solid #ddd6fe
    }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main">
        <div class="page-header">
            <h1> Transfer Antar Akun</h1>
            <p>Pindahkan saldo dari satu akun ke akun lain</p>
        </div>
        <div class="form-wrapper">
            <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-title">Detail Transfer</div>

                <div class="preview-box">
                    <div class="preview-akun" id="previewDari">Pilih akun asal</div>
                    <div class="preview-arrow">➡️</div>
                    <div class="preview-akun" id="previewKe">Pilih akun tujuan</div>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label>Dari Akun (Asal)</label>
                        <select name="dari_akun" id="dariAkun" onchange="updatePreview()">
                            <option value="">-- Pilih Akun Asal --</option>
                            <?php foreach ($akunList as $akun): ?>
                            <option value="<?= $akun['id'] ?>" data-nama="<?= htmlspecialchars($akun['nama_akun']) ?>"
                                data-saldo="<?= $akun['saldo_awal'] ?>"
                                <?= (isset($_POST['dari_akun']) && $_POST['dari_akun'] == $akun['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($akun['nama_akun']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="saldo-info" id="saldoAsal"></div>
                    </div>

                    <div class="form-group">
                        <label>Ke Akun (Tujuan)</label>
                        <select name="ke_akun" id="keAkun" onchange="updatePreview()">
                            <option value="">-- Pilih Akun Tujuan --</option>
                            <?php foreach ($akunList as $akun): ?>
                            <option value="<?= $akun['id'] ?>" data-nama="<?= htmlspecialchars($akun['nama_akun']) ?>"
                                <?= (isset($_POST['ke_akun']) && $_POST['ke_akun'] == $akun['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($akun['nama_akun']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Jumlah (Rp)</label>
                        <input type="number" name="jumlah" placeholder="Masukkan nominal" min="1"
                            value="<?= htmlspecialchars($_POST['jumlah'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" name="tanggal"
                            value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div class="form-group">
                        <label>Keterangan (Opsional)</label>
                        <textarea name="keterangan"
                            placeholder="Contoh: Transfer kas ke BCA..."><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">🔁 Proses Transfer</button>
                    <div class="note">* Saldo akun asal berkurang & akun tujuan bertambah otomatis</div>
                </form>
            </div>
        </div>
    </div>
    <script>
    function updatePreview() {
        const dari = document.getElementById('dariAkun');
        const ke = document.getElementById('keAkun');
        const optDari = dari.options[dari.selectedIndex];
        const optKe = ke.options[ke.selectedIndex];

        document.getElementById('previewDari').textContent = dari.value ? optDari.getAttribute('data-nama') :
            'Pilih akun asal';
        document.getElementById('previewKe').textContent = ke.value ? optKe.getAttribute('data-nama') :
            'Pilih akun tujuan';

        const saldo = optDari.getAttribute('data-saldo');
        const box = document.getElementById('saldoAsal');
        if (saldo && dari.value) {
            box.textContent = '💳 Saldo tersedia: Rp ' + parseInt(saldo).toLocaleString('id-ID');
            box.classList.add('show');
        } else {
            box.classList.remove('show');
        }
    }
    window.onload = function() {
        updatePreview();
    }
    </script>
</body>

</html>