<?php
/**
 * BAGIAN ATAS HALAMAN DASHBOARD
 * Berisi <head>, sidebar menu, dan judul halaman.
 *
 * Variabel yang wajib di-set oleh halaman SEBELUM include file ini:
 *   $activePage   → menu yang aktif: 'home' | 'timer' | 'progress' | 'planner' | 'chat'
 *   $pageTitle    → judul halaman, contoh: 'Study timer'
 *   $pageSubtitle → teks kecil di bawah judul
 *   $today        → tanggal hari ini (dari getToday())
 * Opsional:
 *   $pageHeading  → isi <h1> kalau berbeda dari $pageTitle
 *
 * Jangan lupa include footer.php di akhir halaman.
 */

// Daftar menu. Dipakai untuk sidebar (desktop) dan bottom nav (HP) di footer.php.
$menuItems = [
    'home'     => ['icon' => '⌂', 'label' => 'Home',          'shortLabel' => 'Home'],
    'timer'    => ['icon' => '◷', 'label' => 'Study timer',   'shortLabel' => 'Timer'],
    'progress' => ['icon' => '▥', 'label' => 'Your progress', 'shortLabel' => 'Progress'],
    'planner'  => ['icon' => '▦', 'label' => 'Planner',       'shortLabel' => 'Planner'],
    'chat'     => ['icon' => '✧', 'label' => 'AI study chat', 'shortLabel' => 'AI Chat'],
];

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Analyzer | <?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('assets/css/dashboard.css')) ?>">
</head>
<body>
    <div class="app-shell">
        <!-- ===== SIDEBAR (tampil di layar lebar) ===== -->
        <aside class="sidebar">
            <a class="brand" href="home.php" aria-label="Study Analyzer home">
                <img class="brand-logo" src="assets/images/logo-dashboard.png" alt="Study Analyzer">
            </a>
            <p class="nav-label">WORKSPACE</p>
            <?php foreach ($menuItems as $menuPage => $menu): ?>
                <a class="nav-link <?= $menuPage === $activePage ? 'active' : '' ?>" href="<?= $menuPage ?>.php" <?= $menuPage === $activePage ? 'aria-current="page"' : '' ?>><span aria-hidden="true"><?= $menu['icon'] ?></span> <?= e($menu['label']) ?></a>
            <?php endforeach; ?>

            <div class="sidebar-bottom">
                <div class="account-card">
                    <div class="avatar"><?= e(strtoupper(substr($_SESSION['name'], 0, 1))) ?></div>
                    <div class="account-copy">
                        <strong><?= e($_SESSION['name']) ?></strong>
                        <span><?= e($_SESSION['email']) ?></span>
                    </div>
                </div>
                <form action="auth/logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button class="logout-button" type="submit"><span aria-hidden="true">↪</span> Log out</button>
                </form>
            </div>
        </aside>

        <!-- ===== ISI HALAMAN ===== -->
        <main class="main-content">
            <header class="page-header">
                <div>
                    <p class="eyebrow"><?= e($today->format('l, F j')) ?></p>
                    <h1><?= e($pageHeading ?? $pageTitle) ?> <span class="wave" aria-hidden="true">✦</span></h1>
                    <p class="subtitle"><?= e($pageSubtitle) ?></p>
                </div>
                <div class="header-date"><span class="date-dot"></span>Your focus zone</div>
            </header>
