<?php

/**
 * Konfigurasi koneksi database
 * Menggunakan MySQLi (MySQL Improved)
 */

// Konstanta koneksi database
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // default XAMPP
define('DB_PASS', ''); // default XAMPP kosong
define('DB_NAME', 'db_project_management');

// Fungsi untuk membuat koneksi database
function getDBConnection()
{
    // Membuat koneksi mysqli
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Cek koneksi
    if ($conn->connect_error) {
        // Jangan tampilkan error detail di production, gunakan log
        die("Koneksi database gagal. Silakan hubungi administrator.");
    }

    // Set charset UTF-8 untuk mendukung karakter Indonesia
    $conn->set_charset("utf8mb4");

    return $conn;
}

// Fungsi untuk menutup koneksi
function closeDBConnection($conn)
{
    if ($conn) {
        $conn->close();
    }
}

// Test koneksi (opsional, bisa dihapus di production)
// $test_conn = getDBConnection();
// echo "Koneksi berhasil!";
// closeDBConnection($test_conn);

?>