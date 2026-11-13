<?php
/**
 * HALAMAN PLANNER (kalender rencana belajar)
 *
 * Alamat: planner.php?view=month&date=2026-11-13
 *   view → 'day' | 'week' | 'month' (default: month)
 *   date → tanggal yang dipilih (default: hari ini)
 *
 * Tambah/hapus rencana dikerjakan oleh assets/js/planner.js → api/planner.php.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/study_stats.php';
require_once __DIR__ . '/includes/planner_data.php';
requireLogin();

// ----- 1. Baca pilihan tampilan & tanggal dari URL -----
$userId = currentUserId();
$today = getToday($conn);
$todayDate = $today->format('Y-m-d');

$view = $_GET['view'] ?? 'month';
if (!in_array($view, ['day', 'week', 'month'], true)) {
    $view = 'month';
}
$focusDate = parseDate((string) ($_GET['date'] ?? '')) ?? $today;
$selectedDate = $focusDate->format('Y-m-d');

// ----- 2. Ambil data dari database -----
$dailyGoal = getStudyPreferences($conn, $userId)['daily_goal'];

[$rangeStart, $rangeEnd] = getCalendarRange($view, $focusDate);
$calendarDates = getDatesBetween($rangeStart, $rangeEnd);
$plansByDate = getPlansByDate($conn, $userId, $rangeStart, $rangeEnd);
$minutesByDate = getStudyMinutesByDate($conn, $userId, $rangeStart, $rangeEnd);
$habitBars = getHabitBars($view, $focusDate, $calendarDates, $minutesByDate);
$upcomingPlans = getUpcomingPlans($conn, $userId, $today);

$selectedPlans = $plansByDate[$selectedDate] ?? [];
$selectedMinutes = $minutesByDate[$selectedDate] ?? 0;

[$previousDate, $nextDate] = getPreviousAndNextDate($view, $focusDate);
$periodLabel = getPeriodLabel($view, $rangeStart, $rangeEnd, $focusDate);

// Tinggi batang grafik dihitung relatif terhadap batang tertinggi.
$habitScale = max(1, max(array_column($habitBars, 'minutes')));

// ----- 3. Tampilkan halaman -----
$activePage = 'planner';
$pageTitle = 'Study planner';
$pageSubtitle = 'Make a plan';
$pageScript = 'planner.js';
require __DIR__ . '/includes/header.php';
?>

<section class="planner-page">
    <!-- Rencana yang akan datang + countdown -->
    <article class="next-plan-card upcoming-plans-card">
        <span class="next-plan-icon" aria-hidden="true">◷</span>
        <?php if ($upcomingPlans): ?>
            <div class="next-plan-copy">
                <span class="eyebrow">UPCOMING STUDY PLANS</span>
                <strong><?= count($upcomingPlans) ?> <?= count($upcomingPlans) === 1 ? 'plan' : 'plans' ?> scheduled</strong>
                <span>Countdowns update live for every timed plan.</span>
            </div>
            <ul class="upcoming-plan-list">
                <?php foreach ($upcomingPlans as $plan): ?>
                    <li class="upcoming-plan-item">
                        <span class="upcoming-plan-details">
                            <strong><?= e($plan['title']) ?></strong>
                            <span><?= e(date('D, M j', strtotime($plan['date']))) ?><?= $plan['time'] === null ? ' · All day' : ' · ' . e(date('g:i A', strtotime($plan['time']))) ?></span>
                        </span>
                        <?php if ($plan['timestamp'] !== null): ?>
                            <span class="countdown-clock" data-countdown="<?= e(gmdate(DATE_ATOM, $plan['timestamp'])) ?>">Calculating…</span>
                        <?php else: ?>
                            <span class="countdown-clock"><?= $plan['days_until'] === 0 ? 'Today' : e($plan['days_until'] . ' days') ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="next-plan-copy">
                <span class="eyebrow">UPCOMING STUDY PLANS</span>
                <strong>No upcoming study plans</strong>
                <span>Add something to your calendar to get started.</span>
            </div>
        <?php endif; ?>
    </article>

    <!-- Pilihan tampilan (Day/Week/Month) + navigasi tanggal -->
    <div class="planner-toolbar">
        <div class="view-switch" role="group" aria-label="Calendar view">
            <?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $viewName => $viewLabel): ?>
                <a class="view-switch-link <?= $view === $viewName ? 'active' : '' ?>" href="planner.php?view=<?= $viewName ?>&amp;date=<?= e($selectedDate) ?>" <?= $view === $viewName ? 'aria-current="page"' : '' ?>><?= $viewLabel ?></a>
            <?php endforeach; ?>
        </div>
        <div class="calendar-controls">
            <a class="calendar-arrow" href="planner.php?view=<?= $view ?>&amp;date=<?= $previousDate->format('Y-m-d') ?>" aria-label="Previous <?= $view ?>">‹</a>
            <strong><?= e($periodLabel) ?></strong>
            <a class="calendar-arrow" href="planner.php?view=<?= $view ?>&amp;date=<?= $nextDate->format('Y-m-d') ?>" aria-label="Next <?= $view ?>">›</a>
            <a class="today-link" href="planner.php?view=<?= $view ?>&amp;date=<?= $todayDate ?>">Today</a>
        </div>
    </div>

    <div class="planner-content-grid">
        <!-- Kalender -->
        <article class="panel calendar-panel">
            <?php if ($view === 'month'): ?>
                <div class="month-calendar">
                    <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday): ?>
                        <div class="calendar-weekday"><?= $weekday ?></div>
                    <?php endforeach; ?>
                    <?php foreach ($calendarDates as $day): ?>
                        <?php
                        $date = $day->format('Y-m-d');
                        $dayPlans = $plansByDate[$date] ?? [];
                        $dayMinutes = $minutesByDate[$date] ?? 0;

                        $classes = ['month-day'];
                        if ($date === $selectedDate) {
                            $classes[] = 'selected';
                        }
                        if ($day->format('Y-m') !== $focusDate->format('Y-m')) {
                            $classes[] = 'outside-month';
                        }
                        if ($date === $todayDate) {
                            $classes[] = 'today';
                        }
                        if ($dayMinutes > 0) {
                            $classes[] = 'studied';
                        }
                        ?>
                        <a class="<?= implode(' ', $classes) ?>" href="planner.php?view=month&amp;date=<?= $date ?>">
                            <span class="month-day-number"><?= $day->format('j') ?></span>
                            <?php if ($dayMinutes > 0): ?><span class="month-day-minutes"><?= $dayMinutes ?>m studied</span><?php endif; ?>
                            <?php if ($dayPlans): ?><span class="month-day-plan"><?= e($dayPlans[0]['title']) ?><?= count($dayPlans) > 1 ? ' +' . (count($dayPlans) - 1) : '' ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($view === 'week'): ?>
                <div class="week-calendar">
                    <?php foreach ($calendarDates as $day): ?>
                        <?php
                        $date = $day->format('Y-m-d');
                        $dayPlans = $plansByDate[$date] ?? [];
                        $dayMinutes = $minutesByDate[$date] ?? 0;
                        ?>
                        <a class="week-day <?= $date === $selectedDate ? 'selected' : '' ?> <?= $date === $todayDate ? 'today' : '' ?>" href="planner.php?view=week&amp;date=<?= $date ?>">
                            <span class="week-day-name"><?= $day->format('D') ?></span>
                            <span class="week-day-number"><?= $day->format('j') ?></span>
                            <span class="week-day-study"><?= $dayMinutes > 0 ? $dayMinutes . ' min' : 'No study' ?></span>
                            <?php foreach (array_slice($dayPlans, 0, 3) as $plan): ?>
                                <span class="week-plan-chip"><?= $plan['time'] ? e(date('g:i A', strtotime($plan['time']))) . ' · ' : '' ?><?= e($plan['title']) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($dayPlans) > 3): ?><span class="week-plan-more">+<?= count($dayPlans) - 3 ?> more</span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <div class="day-agenda">
                    <div class="day-agenda-date">
                        <span><?= $focusDate->format('D') ?></span>
                        <strong><?= $focusDate->format('j') ?></strong>
                        <small><?= $focusDate->format('F Y') ?></small>
                    </div>
                    <div class="day-agenda-summary">
                        <strong><?= $selectedMinutes ?> minutes studied</strong>
                        <span><?= count($selectedPlans) ?> <?= count($selectedPlans) === 1 ? 'plan' : 'plans' ?> for this day</span>
                    </div>
                </div>
            <?php endif; ?>
            <div class="calendar-legend"><span class="legend-study-dot"></span> Study completed <span class="legend-plan-dot"></span> Planned session</div>
        </article>

        <!-- Rencana di tanggal yang dipilih + form tambah rencana -->
        <aside class="planner-side-column">
            <article class="panel planner-event-panel">
                <div class="panel-heading">
                    <div><p class="eyebrow"><?= $focusDate->format('M j') ?></p><h2><?= $view === 'day' ? 'Today’s plan' : 'Selected day' ?></h2></div>
                    <span class="history-icon" aria-hidden="true">✎</span>
                </div>
                <?php if ($selectedPlans): ?>
                    <ul class="planner-event-list">
                        <?php foreach ($selectedPlans as $plan): ?>
                            <li class="planner-event">
                                <span class="planner-event-mark" aria-hidden="true"></span>
                                <span class="planner-event-copy">
                                    <strong><?= e($plan['title']) ?></strong>
                                    <span><?= $plan['time'] ? e(date('g:i A', strtotime($plan['time']))) : 'All day' ?></span>
                                    <?php if ($plan['notes'] !== ''): ?><small><?= e($plan['notes']) ?></small><?php endif; ?>
                                </span>
                                <button class="delete-plan-button" type="button" data-delete-plan="<?= $plan['id'] ?>" aria-label="Delete <?= e($plan['title']) ?>">×</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="planner-empty-day">No study plans on this day yet.</p>
                <?php endif; ?>

                <form class="planner-form" id="planner-form">
                    <label for="plan-title">Add a study plan</label>
                    <input id="plan-title" name="title" type="text" maxlength="120" placeholder="e.g. Review biology notes" required>
                    <div class="planner-form-row">
                        <label>Date <input id="plan-date" name="date" type="date" min="<?= $todayDate ?>" max="<?= $today->modify('+2 years')->format('Y-m-d') ?>" value="<?= $selectedDate ?>" required></label>
                        <label>Time <span>(optional)</span><input id="plan-time" name="time" type="time"></label>
                    </div>
                    <label for="plan-notes">Notes <span>(optional)</span></label>
                    <textarea id="plan-notes" name="notes" maxlength="500" rows="2" placeholder="What do you want to cover?"></textarea>
                    <button class="button button-primary" id="save-plan-button" type="submit">Add to planner</button>
                    <p class="timer-message" id="planner-message" role="status"></p>
                </form>
            </article>
        </aside>
    </div>

    <!-- Grafik kebiasaan belajar -->
    <article class="panel habit-panel">
        <div class="panel-heading">
            <div><p class="eyebrow">YOUR STUDY HABIT</p><h2>Study time · <?= ucfirst($view) ?> view</h2></div>
            <span class="habit-goal-note">Daily goal: <?= $dailyGoal ?> min</span>
        </div>
        <div class="habit-chart <?= $view === 'month' ? 'habit-chart-month' : '' ?> <?= $view === 'day' ? 'habit-chart-day' : '' ?>">
            <?php foreach ($habitBars as $bar): ?>
                <?php $barHeight = $bar['minutes'] > 0 ? max(6, (int) round($bar['minutes'] / $habitScale * 100)) : 0; ?>
                <div class="habit-bar-item" <?= isset($bar['date']) ? 'title="' . e(date('M j', strtotime($bar['date']))) . '"' : '' ?>>
                    <span class="habit-bar-value"><?= $bar['minutes'] ?>m</span>
                    <div class="habit-bar-track"><span class="habit-bar-fill <?= $bar['minutes'] >= $dailyGoal ? 'goal-met' : '' ?>" style="height: <?= $barHeight ?>%"></span></div>
                    <span class="habit-bar-label"><?= e($bar['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
