<?php
/**
 * PROSES LOGIN
 * Menerima form dari index.php. Kalau email & password cocok → masuk ke home.php.
 * Kalau salah → kembali ke index.php dengan pesan error.
 */
require_once __DIR__ . '/../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php');
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

try {
    $query = $conn->prepare('SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1');
    $query->bind_param('s', $email);
    $query->execute();
    $query->bind_result($userId, $name, $storedEmail, $passwordHash);
    $userFound = $query->fetch();
    $query->close();

    // password_verify() mencocokkan password dengan hash yang tersimpan di database.
    if ($userFound && is_string($passwordHash) && password_verify($password, $passwordHash)) {
        // Buat ID session baru setelah login (mencegah session fixation).
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $userId;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $storedEmail;
        redirect('../home.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log('Login failed: ' . $e->getMessage());
}

$_SESSION['login_error'] = 'incorrect email or password.';
$_SESSION['active_form'] = 'login';
redirect('../index.php');
