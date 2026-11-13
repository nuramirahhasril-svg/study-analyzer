<?php
/**
 * PROSES REGISTER (daftar akun baru)
 * Menerima form dari index.php, menyimpan user baru ke tabel users,
 * lalu kembali ke index.php.
 */
require_once __DIR__ . '/../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php');
}

/** Simpan pesan error, lalu kembali ke form register. */
function backToRegisterWithError(string $message): void
{
    $_SESSION['register_error'] = $message;
    $_SESSION['active_form'] = 'register';
    redirect('../index.php');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    backToRegisterWithError('Please enter your name, a valid email, and password.');
}

try {
    // Cek apakah email sudah dipakai.
    $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->bind_param('s', $email);
    $check->execute();
    $check->store_result();
    $emailTaken = $check->num_rows > 0;
    $check->close();

    if ($emailTaken) {
        backToRegisterWithError('Email is already registered.');
    }

    // Password TIDAK disimpan apa adanya, tapi di-hash dulu.
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $conn->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
    $insert->bind_param('sss', $name, $email, $passwordHash);
    $insert->execute();
    $insert->close();
} catch (mysqli_sql_exception $e) {
    error_log('Register failed: ' . $e->getMessage());
    backToRegisterWithError('Failed to save user. Please check your connection and database tables.');
}

// Berhasil daftar → kembali ke form login.
redirect('../index.php');
