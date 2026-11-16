<?php
/**
 * HALAMAN LOGIN & REGISTER (halaman pertama yang dibuka)
 *
 * Form login dikirim ke auth/login.php, form register ke auth/register.php.
 * Kalau ada error, file tersebut menyimpan pesan di $_SESSION lalu kembali ke sini.
 */
require_once __DIR__ . '/includes/init.php';

// Sudah login (session masih berlaku)? Langsung ke dashboard, jangan tampilkan form login lagi.
if (isLoggedIn()) {
    redirect('home.php');
}

// Ambil pesan error (kalau ada), lalu hapus supaya tidak muncul lagi saat refresh.
$loginError = $_SESSION['login_error'] ?? '';
$registerError = $_SESSION['register_error'] ?? '';
$activeForm = $_SESSION['active_form'] ?? 'login';
unset($_SESSION['login_error'], $_SESSION['register_error'], $_SESSION['active_form']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Analyzer | Sign in</title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/login.css')) ?>">
</head>
<body>
    <div class="container">
        <!-- Form LOGIN -->
        <div class="form-box <?= $activeForm === 'login' ? 'active' : '' ?>" id="login-form">
            <form action="auth/login.php" method="post">
                <h2>Login</h2>
                <?php if ($loginError): ?><p class="error-message"><?= e($loginError) ?></p><?php endif; ?>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
                <p>Don't have an account? <a href="#" onclick="showForm('register-form')">Register</a></p>
            </form>
        </div>

        <!-- Form REGISTER -->
        <div class="form-box <?= $activeForm === 'register' ? 'active' : '' ?>" id="register-form">
            <form action="auth/register.php" method="post">
                <h2>Register</h2>
                <?php if ($registerError): ?><p class="error-message"><?= e($registerError) ?></p><?php endif; ?>
                <input type="text" name="name" placeholder="Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Register</button>
                <p>Already have an account? <a href="#" onclick="showForm('login-form')">Login</a></p>
            </form>
        </div>
    </div>

    <script src="<?= e(asset('assets/js/login.js')) ?>"></script>
</body>
</html>
