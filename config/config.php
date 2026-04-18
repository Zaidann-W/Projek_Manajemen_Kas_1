<?php
// Database config — otomatis pakai ENV vars di Vercel, fallback ke localhost untuk development
$host   = getenv('DB_HOST') ?: "localhost";
$dbname = getenv('DB_NAME') ?: "umkm_manajemen_kas_db";
$user   = getenv('DB_USER') ?: "root";
$pass   = getenv('DB_PASS') ?: "";

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";
    
    // Tambah SSL jika di production (Vercel + cloud DB)
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $conn = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
