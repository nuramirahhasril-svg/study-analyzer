<?php
/**
 * MEMBUAT TABEL DATABASE OTOMATIS (khusus Railway / server)
 *
 * Dijalankan oleh docker/start.sh setiap kali server dinyalakan:
 *     php database/install.php
 *
 * Isinya diambil dari database.sql, tapi perintah CREATE DATABASE dan USE dilewati,
 * karena di Railway database-nya sudah dibuatkan (namanya ada di MYSQL_URL).
 * Aman dijalankan berkali-kali: tabel yang sudah ada tidak diubah (CREATE TABLE IF NOT EXISTS).
 *
 * Di XAMPP file ini tidak perlu dipakai, cukup import database.sql lewat phpMyAdmin.
 */

// Hanya boleh dijalankan dari terminal, tidak dari browser.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/database.php'; // membuat $conn

$sql = file_get_contents(__DIR__ . '/database.sql');

// Buang baris komentar SQL (diawali "--").
$sql = preg_replace('/^\s*--.*$/m', '', $sql);

// Jalankan perintah satu per satu (dipisah dengan titik koma).
foreach (explode(';', $sql) as $statement) {
    $statement = trim($statement);

    if ($statement === '' || preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $statement)) {
        continue;
    }

    $conn->query($statement);
}

echo "Tabel database siap.\n";
