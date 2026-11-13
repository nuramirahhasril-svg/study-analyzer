<?php
/**
 * HALAMAN TIMER BELAJAR
 * Tampilan timer. Logika tombol start/pause/finish ada di assets/js/timer.js,
 * yang mengirim data ke api/timer.php.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/study_stats.php';
requireLogin();

// ----- 1. Ambil data dari database -----
$userId = currentUserId();
$today = getToday($conn);

$preferences = getStudyPreferences($conn, $userId);
$todayMinutes = getMinutesStudiedToday($conn, $userId);
$weeklyAverage = getWeeklyAverageMinutes($conn, $userId);
$suggestion = getTodaySuggestion($conn, $userId, $preferences, $todayMinutes, $weeklyAverage);
$activeSession = getActiveSession($conn, $userId);

// Durasi awal di kotak input: durasi sesi aktif, atau saran hari ini, atau durasi default.
if ($activeSession) {
    $durationValue = (int) ceil($activeSession['planned_seconds'] / 60);
} elseif ($suggestion['minutes'] > 0) {
    $durationValue = $suggestion['minutes'];
} else {
    $durationValue = $preferences['default_session'];
}

// ----- 2. Tampilkan halaman -----
$activePage = 'timer';
$pageTitle = 'Study timer';
$pageSubtitle = 'Time to focus';
$pageScript = 'timer.js';
$jsData = ['activeSession' => $activeSession];
require __DIR__ . '/includes/header.php';
?>

<section class="single-page-content">
    <article class="panel timer-panel" id="timer">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">FOCUS MODE</p>
                <h2>Your study session</h2>
            </div>
            <span class="live-indicator"><span></span> STAY PRESENT</span>
        </div>

        <!-- Input mata pelajaran & durasi -->
        <div class="timer-setup" id="timer-setup">
            <label for="subject">What are you working on?</label>
            <input id="subject" type="text" maxlength="100" placeholder="e.g. Biology, Chapter 4" value="<?= $activeSession ? e($activeSession['subject']) : '' ?>">
            <label for="duration">Focus duration</label>
            <div class="duration-field">
                <input id="duration" type="number" min="1" max="120" step="1" value="<?= $durationValue ?>" <?= $activeSession ? 'disabled' : '' ?>>
                <span>minutes</span>
            </div>
        </div>

        <!-- Angka timer (diisi oleh timer.js) -->
        <div class="timer-display" aria-hidden="true">
            <span class="timer-number" id="timer-minutes">00</span>
            <span class="timer-separator" aria-hidden="true">:</span>
            <span class="timer-number" id="timer-seconds">00</span>
        </div>
        <span class="visually-hidden" id="timer-display" aria-live="polite">00 minutes 00 seconds</span>
        <p class="timer-caption" id="timer-caption">One focused session at a time. You've got this.</p>

        <div class="timer-actions">
            <button class="button button-primary" type="button" id="start-button"><?= $activeSession ? ($activeSession['status'] === 'paused' ? 'Resume session' : 'Pause session') : 'Start focusing' ?></button>
            <button class="button button-secondary" type="button" id="finish-button" <?= $activeSession ? '' : 'hidden' ?>>Finish session</button>
        </div>
        <p class="timer-message" id="timer-message" role="status"></p>

        <div class="suggestion-callout">
            <span class="stat-icon peach" aria-hidden="true">✎</span>
            <p><strong>Today's suggestion: <?= $suggestion['minutes'] ?> minutes.</strong> <?= e($suggestion['reason']) ?></p>
        </div>
    </article>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
