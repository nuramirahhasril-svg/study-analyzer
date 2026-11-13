<?php
/**
 * PROSES LOGOUT
 * Dipanggil oleh tombol "Log out" di sidebar (lihat includes/header.php).
 */
require_once __DIR__ . '/../includes/init.php';

// Logout hanya boleh lewat POST + token CSRF yang benar.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isValidCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid logout request.');
}

session_unset();
session_destroy();
redirect('../index.php');
