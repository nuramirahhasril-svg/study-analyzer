<?php
/**
 * KONEKSI DATABASE
 *
 * Di laptop (XAMPP): pakai pengaturan di bagian 1.
 * Di Railway: isi variabel MYSQL_URL di service aplikasi, pengaturan di bagian 1 otomatis diganti.
 */

// ---- 1. Pengaturan XAMPP (ubah kalau setting MySQL kamu berbeda) ----
// Default XAMPP: user "root" tanpa password.
$dbHost = 'localhost';
$dbPort = 3306;
$dbUser = 'root';
$dbPassword = '';
$dbName = 'study_analyzer';

// ---- 2. Pengaturan Railway (hanya dipakai kalau variabel MYSQL_URL ada) ----
// Format MYSQL_URL: mysql://user:password@host:port/nama_database
$databaseUrl = getenv('MYSQL_URL');
if ($databaseUrl) {
    $url = parse_url($databaseUrl);
    $dbHost = $url['host'] ?? $dbHost;
    $dbPort = (int) ($url['port'] ?? $dbPort);
    $dbUser = urldecode($url['user'] ?? $dbUser);
    $dbPassword = urldecode($url['pass'] ?? '');
    $dbName = ltrim($url['path'] ?? '', '/') ?: $dbName;
}

// Zona waktu untuk NOW() dan CURDATE() di MySQL, misalnya "+07:00" untuk WIB.
// Di XAMPP biarkan kosong: MySQL sudah memakai jam laptop.
// Di Railway sudah diisi otomatis lewat Dockerfile (MySQL di Railway memakai jam UTC).
$dbTimezone = getenv('DB_TIMEZONE');

// ---- 3. Sambungkan ke database ----
// Kalau ada query yang error, PHP akan melempar exception (bisa ditangkap pakai try/catch).
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName, $dbPort);
    $conn->set_charset('utf8mb4');

    if ($dbTimezone) {
        $conn->query("SET time_zone = '" . $conn->real_escape_string($dbTimezone) . "'");
    }
} catch (mysqli_sql_exception $e) {
    error_log('Database Error: ' . $e->getMessage());

    if (PHP_SAPI === 'cli') {
        exit(1); // dijalankan dari terminal (database/install.php): beri tanda gagal
    }
    die('Database connection failed. Please check your connection and database tables.');
}
