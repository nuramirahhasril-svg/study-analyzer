<?php
/**
 * HALAMAN PROGRESS
 * Menit belajar hari ini, rata-rata 7 hari, dan sesi terakhir.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/study_stats.php';
requireLogin();

// ----- 1. Ambil data dari database -----
$userId = currentUserId();
$today = getToday($conn);

$preferences = getStudyPreferences($conn, $userId);
$dailyGoal = $preferences['daily_goal'];
$todayMinutes = getMinutesStudiedToday($conn, $userId);
$dailyGoalProgress = min(100, (int) round($todayMinutes / max(1, $dailyGoal) * 100));
$weeklyAverage = getWeeklyAverageMinutes($conn, $userId);
$recentSessions = getRecentSessions($conn, $userId);

// ----- 2. Tampilkan halaman -----
$activePage = 'progress';
$pageTitle = 'Your progress';
$pageSubtitle = 'Keep going';
require __DIR__ . '/includes/header.php';
?>

<section class="progress-overview" aria-label="Your weekly progress">
    <div class="progress-summary-grid">
        <article class="stat-card">
            <div class="stat-icon mint" aria-hidden="true">◷</div>
            <div class="stat-label">Studied today</div>
            <div class="stat-value"><?= $todayMinutes ?><span> min</span></div>
            <div class="stat-note">of your <?= $dailyGoal ?> min daily goal</div>
            <div class="progress-track"><span style="width: <?= $dailyGoalProgress ?>%"></span></div>
        </article>
        <article class="stat-card">
            <div class="stat-icon lilac" aria-hidden="true">✦</div>
            <div class="stat-label">7-day daily average</div>
            <div class="stat-value"><?= $weeklyAverage ?><span> min</span></div>
            <div class="stat-note">Your last seven days of study</div>
        </article>
    </div>

    <article class="panel history-panel">
        <div class="panel-heading">
            <div><p class="eyebrow">KEEP IT GOING</p><h2>Recent sessions</h2></div>
        </div>
        <?php require __DIR__ . '/includes/session_list.php'; ?>
    </article>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
