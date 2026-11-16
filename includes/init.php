<?php
/**
 * FILE PEMBUKA
 *
 * Di-include paling atas oleh SEMUA file PHP di project ini.
 * Tugasnya: memulai session, membuka koneksi database ($conn),
 * dan memuat fungsi-fungsi bantuan.
 */

// ---------------------------------------------------------------------------
// Login tetap tersimpan selama 30 hari ("login sekali saja").
//
// Bawaan PHP: cookie session hilang saat browser/aplikasi ditutup, dan data
// session dihapus server setelah 24 menit tidak dipakai. Akibatnya user
// (terutama di aplikasi Android) harus login lagi setiap membuka aplikasi.
// ---------------------------------------------------------------------------
const SESSION_LIFETIME = 60 * 60 * 24 * 30; // 30 hari (dalam detik)

// Simpan file session di folder sendiri (storage/sessions), supaya tidak ikut
// dihapus oleh "pembersihan session" aplikasi lain di server yang memakai batas
// 24 menit. Folder ini dikunci dari browser oleh storage/.htaccess.
// Kalau folder tidak bisa ditulis, PHP memakai folder session bawaan server.
$sessionDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0700, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    session_save_path($sessionDir);
}

ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

// Perpanjang cookie setiap kali user membuka halaman, jadi login baru
// kedaluwarsa setelah 30 hari TIDAK dipakai (bukan 30 hari sejak login).
if (!empty($_SESSION['user_id']) && !headers_sent()) {
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => time() + SESSION_LIFETIME,
        'path' => $params['path'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
        'secure' => $params['secure'],
    ]);
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
