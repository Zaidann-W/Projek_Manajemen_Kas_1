<?php
include __DIR__ . '/../services/authservice.php';
include __DIR__ . '/../config/config.php';
require __DIR__ . '/../libs/fpdf.php';
requireLogin();

$userId  = getUserId();
$ownerId = getOwnerUserId();
$bizIds  = getBusinessUserIds();
$biz     = buildInClause($bizIds);

$tipe   = $_GET['tipe'] ?? 'bulanan'; 
$bulan  = $_GET['bulan'] ?? date('Y-m');
$tahun  = $_GET['tahun'] ?? date('Y');
$tgl    = $_GET['tanggal'] ?? date('Y-m-d');

$namaUser = getUserName();
$namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

if ($tipe === 'harian') {
    $where = "t.user_id IN ({$biz['placeholders']}) AND t.tanggal = ?";
    $params = array_merge($biz['values'], [$tgl]);
    $judul = "Laporan Harian - " . date('d F Y', strtotime($tgl));
} elseif ($tipe === 'tahunan') {
    $where = "t.user_id IN ({$biz['placeholders']}) AND YEAR(t.tanggal) = ?";
    $params = array_merge($biz['values'], [$tahun]);
    $judul = "Laporan Tahunan - $tahun";
} else {
    $parts = explode('-', $bulan);
    $y = $parts[0]; $m = $parts[1];
    $where = "t.user_id IN ({$biz['placeholders']}) AND MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ?";
    $params = array_merge($biz['values'], [$m, $y]);
    $judul = "Laporan Bulanan - {$namaBulan[(int)$m]} $y";
}

$stmt = $conn->prepare("
    SELECT t.tanggal, t.tipe, t.jumlah, t.keterangan, a.nama_akun
    FROM transaksi t
    LEFT JOIN akun_tf a ON t.akuntf_id = a.id
    WHERE $where
    ORDER BY t.tanggal ASC, t.id ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalMasuk = 0; $totalKeluar = 0;
foreach ($rows as $r) {
    if ($r['tipe'] === 'pemasukan') $totalMasuk += $r['jumlah'];
    elseif ($r['tipe'] === 'pengeluaran') $totalKeluar += $r['jumlah'];
}
$saldo = $totalMasuk - $totalKeluar;

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

class LaporanPDF extends FPDF {
    function Header() {
        // Logo text
        $this->SetFont('Helvetica', 'B', 20);
        $this->SetTextColor(59, 130, 246);
        $this->Cell(0, 10, 'SmartKas', 0, 1, 'C');
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, 'Sistem Manajemen Keuangan UMKM', 0, 1, 'C');
        $this->Ln(3);
        // Divider line
        $this->SetDrawColor(59, 130, 246);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(6);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, 'Dicetak pada: ' . date('d/m/Y H:i') . '  |  Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function SummaryBox($label, $value, $x, $color) {
        $this->SetXY($x, $this->GetY());
        // Background
        $this->SetFillColor($color[0], $color[1], $color[2]);
        $this->SetDrawColor($color[0], $color[1], $color[2]);
        $this->RoundedRect($x, $this->GetY(), 58, 20, 3, 'DF');
        // Text
        $this->SetXY($x + 4, $this->GetY() + 3);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(50, 5, $label, 0, 2);
        $this->SetFont('Helvetica', 'B', 11);
        $this->Cell(50, 7, $value, 0, 0);
    }

    function RoundedRect($x, $y, $w, $h, $r, $style = '') {
        $k = $this->k;
        $hp = $this->h;
        if ($style == 'F') $op = 'f';
        elseif ($style == 'FD' || $style == 'DF') $op = 'B';
        else $op = 'S';
        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
        $xc = $x + $w - $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        $xc = $x + $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
    }
}

$pdf = new LaporanPDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

$pdf->SetFont('Helvetica', 'B', 14);
$pdf->SetTextColor(30, 30, 30);
$pdf->Cell(0, 8, iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $judul), 0, 1);
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Dibuat oleh: ' . iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $namaUser), 0, 1);
$pdf->Ln(5);

$startY = $pdf->GetY();
$pdf->SummaryBox('Total Pemasukan', rp($totalMasuk), 10, [34, 197, 94]);
$pdf->SetXY(72, $startY);
$pdf->SummaryBox('Total Pengeluaran', rp($totalKeluar), 72, [239, 68, 68]);
$pdf->SetXY(134, $startY);
$saldoColor = $saldo >= 0 ? [59, 130, 246] : [239, 68, 68];
$pdf->SummaryBox($saldo >= 0 ? 'Laba Bersih' : 'Rugi Bersih', rp(abs($saldo)), 134, $saldoColor);
$pdf->SetY($startY + 26);

$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetFillColor(59, 130, 246);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(200, 200, 200);

$w = [12, 24, 56, 40, 24, 34];
$headers = ['No', 'Tanggal', 'Keterangan', 'Akun', 'Tipe', 'Nominal'];
for ($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($w[$i], 8, $headers[$i], 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(40, 40, 40);
$no = 1;
$fill = false;

foreach ($rows as $r) {
    if ($fill) $pdf->SetFillColor(245, 245, 250);
    else $pdf->SetFillColor(255, 255, 255);

    $ket = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $r['keterangan'] ?? '-');
    $akun = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $r['nama_akun'] ?? '-');
    $tipeLabel = $r['tipe'] === 'pemasukan' ? 'Masuk' : ($r['tipe'] === 'pengeluaran' ? 'Keluar' : 'Transfer');

    if ($pdf->GetY() + 7 > 270) {
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(59, 130, 246);
        $pdf->SetTextColor(255, 255, 255);
        for ($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($w[$i], 8, $headers[$i], 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(40, 40, 40);
    }

    $pdf->Cell($w[0], 7, $no++, 1, 0, 'C', $fill);
    $pdf->Cell($w[1], 7, date('d/m/Y', strtotime($r['tanggal'])), 1, 0, 'C', $fill);
    $pdf->Cell($w[2], 7, substr($ket, 0, 40), 1, 0, 'L', $fill);
    $pdf->Cell($w[3], 7, substr($akun, 0, 25), 1, 0, 'L', $fill);

    if ($r['tipe'] === 'pemasukan') $pdf->SetTextColor(22, 163, 74);
    elseif ($r['tipe'] === 'pengeluaran') $pdf->SetTextColor(220, 38, 38);
    else $pdf->SetTextColor(124, 58, 237);
    $pdf->Cell($w[4], 7, $tipeLabel, 1, 0, 'C', $fill);

    $pdf->SetFont('Helvetica', 'B', 8);
    $prefix = $r['tipe'] === 'pemasukan' ? '+' : ($r['tipe'] === 'pengeluaran' ? '-' : '');
    $pdf->Cell($w[5], 7, $prefix . rp($r['jumlah']), 1, 0, 'R', $fill);

    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Ln();
    $fill = !$fill;
}

if (count($rows) === 0) {
    $pdf->SetFillColor(250, 250, 250);
    $pdf->Cell(array_sum($w), 10, 'Tidak ada transaksi pada periode ini', 1, 1, 'C', true);
}

$pdf->Ln(2);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetFillColor(240, 240, 245);
$pdf->Cell($w[0] + $w[1] + $w[2] + $w[3], 8, 'TOTAL', 1, 0, 'R', true);
$pdf->SetTextColor(22, 163, 74);
$pdf->Cell($w[4], 8, '', 1, 0, 'C', true);
$pdf->Cell($w[5], 8, rp($totalMasuk - $totalKeluar), 1, 0, 'R', true);
$pdf->Ln();

$pdf->SetTextColor(40, 40, 40);
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell($w[0] + $w[1] + $w[2] + $w[3], 7, 'Total Pemasukan', 0, 0, 'R');
$pdf->SetTextColor(22, 163, 74);
$pdf->Cell($w[4] + $w[5], 7, '+' . rp($totalMasuk), 0, 1, 'R');

$pdf->SetTextColor(40, 40, 40);
$pdf->Cell($w[0] + $w[1] + $w[2] + $w[3], 7, 'Total Pengeluaran', 0, 0, 'R');
$pdf->SetTextColor(220, 38, 38);
$pdf->Cell($w[4] + $w[5], 7, '-' . rp($totalKeluar), 0, 1, 'R');

$filename = 'SmartKas_' . str_replace(' ', '_', $judul) . '.pdf';
$pdf->Output('D', $filename);
exit;
