<?php
/**
 * HALAMAN HOME (dashboard utama)
 * Ringkasan: target harian, streak, rata-rata 7 hari, saran hari ini, dan sesi terakhir.
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
$remainingMinutes = max(0, $dailyGoal - $todayMinutes);
$dailyGoalProgress = min(100, (int) round($todayMinutes / max(1, $dailyGoal) * 100));

$streakDays = getStudyStreak($conn, $userId, $today);
$weeklyAverage = getWeeklyAverageMinutes($conn, $userId);
$suggestion = getTodaySuggestion($conn, $userId, $preferences, $todayMinutes, $weeklyAverage);
$activeSession = getActiveSession($conn, $userId);
$recentSessions = getRecentSessions($conn, $userId);

// ----- 2. Tampilkan halaman -----
$firstName = explode(' ', trim($_SESSION['name']))[0];
$activePage = 'home';
$pageTitle = 'Home';
$pageHeading = 'Welcome back, ' . $firstName;
$pageSubtitle = 'Make today count.';
require __DIR__ . '/includes/header.php';
?>

<!-- Kartu statistik -->
<section class="stats-grid" aria-label="Study summary">
    <article class="stat-card">
        <div class="stat-icon mint" aria-hidden="true">◷</div>
        <div class="stat-label">Daily goal</div>
        <div class="stat-value"><?= $todayMinutes ?><span> / <?= $dailyGoal ?> min</span></div>
        <div class="stat-note"><?= $remainingMinutes > 0 ? $remainingMinutes . ' minutes left to reach your goal' : 'Daily goal reached. Great work!' ?></div>
        <div class="progress-track"><span style="width: <?= $dailyGoalProgress ?>%"></span></div>
    </article>
    <article class="stat-card">
        <div class="stat-icon peach" aria-hidden="true">✦</div>
        <div class="stat-label">Study streak</div>
        <div class="stat-value"><?= $streakDays ?><span> <?= $streakDays === 1 ? 'day' : 'days' ?></span></div>
        <div class="stat-note"><?= $streakDays > 0 ? 'Keep your learning streak alive!' : 'Finish a session today to start a streak.' ?></div>
    </article>
    <article class="stat-card">
        <div class="stat-icon lilac" aria-hidden="true">✦</div>
        <div class="stat-label">7-day daily average</div>
        <div class="stat-value"><?= $weeklyAverage ?><span> min</span></div>
        <div class="stat-note">A little progress adds up</div>
    </article>
    <article class="stat-card recommendation-card">
        <div class="stat-icon peach" aria-hidden="true">✎</div>
        <div class="stat-label">Today's suggestion</div>
        <div class="stat-value"><?= $suggestion['minutes'] ?><span> min</span></div>
        <div class="stat-note"><?= e($suggestion['reason']) ?></div>
        <a class="text-action" href="timer.php" <?= $suggestion['minutes'] < 1 && !$activeSession ? 'aria-disabled="true" tabindex="-1"' : '' ?>><?= $activeSession ? 'Return to timer' : 'Use suggestion' ?> <span aria-hidden="true">→</span></a>
    </article>
</section>

<section class="content-grid home-content">
    <!-- Ajakan mulai / lanjutkan sesi -->
    <article class="panel home-focus">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">READY WHEN YOU ARE</p>
                <h2><?= $activeSession ? ($activeSession['status'] === 'paused' ? 'Your session is paused' : 'Your session is in progress') : 'Make room for focus' ?></h2>
            </div>
            <span class="history-icon" aria-hidden="true">◷</span>
        </div>
        <div class="home-focus-copy">
            <p><?= $activeSession ? 'You have an ' . e($activeSession['status']) . ' session for ' . e($activeSession['subject']) . '.' : 'Your next study session is only a tap away. Pick a subject and start with a fresh, focused session.' ?></p>
            <a class="button button-primary" href="timer.php"><?= $activeSession ? 'Return to timer' : 'Open study timer' ?> <span aria-hidden="true">→</span></a>
        </div>
    </article>

    <!-- Sesi terakhir -->
    <article class="panel progress-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">KEEP IT GOING</p>
                <h2>Recent sessions</h2>
            </div>
            <span class="history-icon" aria-hidden="true">↗</span>
        </div>
        <?php require __DIR__ . '/includes/session_list.php'; ?>
        <div class="goal-note">
            <span aria-hidden="true">◎</span>
            <p>Your daily goal is <strong><?= $dailyGoal ?> minutes</strong>. Keep showing up for yourself.</p>
        </div>
    </article>
</section>

<a class="planner-promo" href="planner.php">
    <span class="planner-promo-icon" aria-hidden="true">▦</span>
    <span><strong>Plan your next study session</strong><small>Open your calendar, set reminders, and see your study habits.</small></span>
    <span class="planner-promo-arrow" aria-hidden="true">→</span>
</a>

<?php require __DIR__ . '/includes/footer.php'; ?>
