<?php
include '../services/authservice.php';
include '../config/config.php';
requireLogin();

$userId = getUserId();
$bulan  = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$parts  = explode('-', $bulan);
$tahun  = $parts[0];
$bln    = $parts[1];

// Ambil data transaksi
$stmt = $conn->prepare("
    SELECT t.tanggal, t.tipe, t.jumlah, t.keterangan, a.nama_akun
    FROM transaksi t
    LEFT JOIN akun_tf a ON t.akuntf_id = a.id
    WHERE t.user_id = ? AND MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ?
    ORDER BY t.tanggal ASC
");
$stmt->execute([$userId, $bln, $tahun]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set header buat download CSV
$filename = "laporan_" . $bulan . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Header kolom
fputcsv($output, ['Tanggal', 'Keterangan', 'Akun', 'Tipe', 'Jumlah']);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['tanggal'],
        $row['keterangan'],
        $row['nama_akun'] ?? '-',
        $row['tipe'],
        $row['jumlah']
    ]);
}

fclose($output);
exit;
?>