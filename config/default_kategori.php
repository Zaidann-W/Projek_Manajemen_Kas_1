<?php
/**
 * Daftar kategori default UMKM.
 * 
 * Mau tambah/hapus/edit kategori default? Edit di sini saja.
 * File ini dipakai otomatis saat:
 *  - User baru daftar (auth/register.php)
 *  - User klik tombol "+ Tambah Kategori Default" (data/kategoricf.php)
 * 
 * Format: ['masuk'/'keluar', 'Nama Kategori']
 */

$defaultKategori = [

    // ==========================================
    //  PEMASUKAN
    // ==========================================
    ['masuk', 'Penjualan Produk'],
    ['masuk', 'Penjualan Makanan'],
    ['masuk', 'Penjualan Minuman'],
    ['masuk', 'Pesanan Online'],
    ['masuk', 'Pesanan Catering'],
    ['masuk', 'Jasa Layanan'],
    ['masuk', 'Modal Masuk'],
    ['masuk', 'Komisi / Bonus'],
    ['masuk', 'Pendapatan Lain-lain'],

    // ==========================================
    //  PENGELUARAN
    // ==========================================
    ['keluar', 'Belanja Bahan Baku'],
    ['keluar', 'Gaji Karyawan'],
    ['keluar', 'Listrik'],
    ['keluar', 'Air'],
    ['keluar', 'Gas LPG'],
    ['keluar', 'Sewa Tempat'],
    ['keluar', 'Biaya Kemasan'],
    ['keluar', 'Biaya Transportasi'],
    ['keluar', 'Biaya Pemasaran / Iklan'],
    ['keluar', 'Perawatan Peralatan'],
    ['keluar', 'Perlengkapan'],
    ['keluar', 'Biaya tak terduga'],

];
