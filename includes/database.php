<?php
/**
 * KONEKSI DATABASE
 *
 * - Di XAMPP (komputer sendiri): dipakai setting di bawah (user "root" tanpa password).
 * - Di hosting (misalnya InfinityFree): buat file includes/db_hosting.php berisi
 *   4 variabel yang sama dengan data MySQL dari hosting. Kalau file itu ada,
 *   isinya otomatis menggantikan setting XAMPP di bawah.
 * - Di Railway: isi variabel MYSQL_URL di service aplikasi (contoh: ${{MySQL.MYSQL_URL}}).
 *   Kalau variabel itu ada, setting di atas otomatis diganti.
 */
$dbHost = 'localhost';
$dbPort = 3306;
$dbUser = 'root';
$dbPassword = '';
$dbName = 'study_analyzer';

if (is_file(__DIR__ . '/db_hosting.php')) {
    require __DIR__ . '/db_hosting.php';
}

// Railway. Format MYSQL_URL: mysql://user:password@host:port/nama_database
$databaseUrl = getenv('MYSQL_URL');
if ($databaseUrl) {
    $url = parse_url($databaseUrl);
    $dbHost = $url['host'] ?? $dbHost;
    $dbPort = (int) ($url['port'] ?? $dbPort);
    $dbUser = urldecode($url['user'] ?? $dbUser);
    $dbPassword = urldecode($url['pass'] ?? '');
    $dbName = ltrim($url['path'] ?? '', '/') ?: $dbName;
}

// Zona waktu untuk NOW() / CURDATE() di MySQL. "Hari ini", streak, dan target harian
// dihitung pakai jam MySQL, jadi harus jam Malaysia (UTC+8), bukan jam server hosting.
// Di Railway bisa diganti lewat variabel DB_TIMEZONE.
$dbTimeZone = getenv('DB_TIMEZONE') ?: ($dbTimeZone ?? '+08:00');

// Kalau ada query yang error, PHP akan melempar exception (bisa ditangkap pakai try/catch).
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName, $dbPort);
    $conn->set_charset('utf8mb4');
    $setZone = $conn->prepare('SET time_zone = ?');
    $setZone->bind_param('s', $dbTimeZone);
    $setZone->execute();
    $setZone->close();
} catch (mysqli_sql_exception $e) {
    error_log('Database Error: ' . $e->getMessage());

    if (PHP_SAPI === 'cli') {
        exit(1); // dijalankan dari terminal (database/install.php): beri tanda gagal
    }
    die('Database connection failed. Please check your connection and database tables.');
}
