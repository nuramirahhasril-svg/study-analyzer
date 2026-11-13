<?php
/**
 * DATA UNTUK HALAMAN PLANNER
 *
 * Fungsi-fungsi untuk menghitung kalender (hari / minggu / bulan),
 * mengambil rencana belajar, dan menyiapkan grafik kebiasaan belajar.
 * Dipakai oleh planner.php.
 */

/**
 * Rentang tanggal yang tampil di kalender.
 * Hasil: [tanggal awal, tanggal akhir]. Tanggal akhir TIDAK ikut ditampilkan.
 *
 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
 */
function getCalendarRange(string $view, DateTimeImmutable $focusDate): array
{
    if ($view === 'day') {
        $start = $focusDate;
        $end = $start->modify('+1 day');
    } elseif ($view === 'week') {
        $start = $focusDate->modify('monday this week');
        $end = $start->modify('+7 days');
    } else {
        // Tampilan bulan: mulai dari hari Senin sebelum tanggal 1,
        // lalu tampilkan minggu penuh (kelipatan 7 hari).
        $monthStart = $focusDate->modify('first day of this month');
        $daysBeforeMonth = (int) $monthStart->format('N') - 1;
        $visibleDays = (int) (ceil(($daysBeforeMonth + (int) $monthStart->format('t')) / 7) * 7);
        $start = $monthStart->modify('-' . $daysBeforeMonth . ' days');
        $end = $start->modify('+' . $visibleDays . ' days');
    }
    return [$start, $end];
}

/**
 * Semua tanggal dari $start sampai sebelum $end.
 *
 * @return DateTimeImmutable[]
 */
function getDatesBetween(DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $dates = [];
    foreach (new DatePeriod($start, new DateInterval('P1D'), $end) as $day) {
        $dates[] = $day;
    }
    return $dates;
}

/**
 * Rencana belajar dalam rentang tanggal, dikelompokkan per tanggal.
 * Contoh hasil: ['2026-11-13' => [rencana1, rencana2], '2026-11-14' => [...]]
 */
function getPlansByDate(mysqli $conn, int $userId, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $startDate = $start->format('Y-m-d');
    $endDate = $end->format('Y-m-d');

    $query = $conn->prepare(
        'SELECT id, title, notes, plan_date, plan_time
         FROM study_plans
         WHERE user_id = ? AND plan_date >= ? AND plan_date < ?
         ORDER BY plan_date, plan_time IS NULL, plan_time, id
         LIMIT 300'
    );
    $query->bind_param('iss', $userId, $startDate, $endDate);
    $query->execute();
    $query->bind_result($id, $title, $notes, $date, $time);

    $plansByDate = [];
    while ($query->fetch()) {
        $plansByDate[$date][] = [
            'id' => (int) $id,
            'title' => $title,
            'notes' => $notes,
            'date' => $date,
            'time' => $time, // null = sepanjang hari (all day)
        ];
    }
    $query->close();

    return $plansByDate;
}

/**
 * Menit belajar (sesi selesai) per tanggal.
 * Contoh hasil: ['2026-11-13' => 45, '2026-11-14' => 30]
 */
function getStudyMinutesByDate(mysqli $conn, int $userId, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $startDate = $start->format('Y-m-d');
    $endDate = $end->format('Y-m-d');

    $query = $conn->prepare(
        "SELECT DATE(ended_at), SUM(elapsed_seconds)
         FROM study_sessions
         WHERE user_id = ? AND status = 'completed'
           AND ended_at >= ? AND ended_at < ?
         GROUP BY DATE(ended_at)"
    );
    $query->bind_param('iss', $userId, $startDate, $endDate);
    $query->execute();
    $query->bind_result($date, $seconds);

    $minutesByDate = [];
    while ($query->fetch()) {
        $minutesByDate[$date] = (int) floor($seconds / 60);
    }
    $query->close();

    return $minutesByDate;
}

/**
 * Batang-batang untuk grafik "Your study habit".
 * - Tampilan bulan  → 1 batang per minggu ("Week 1", "Week 2", ...)
 * - Tampilan minggu → 1 batang per hari ("Mon", "Tue", ...)
 * - Tampilan hari   → 1 batang ("Today")
 *
 * @return list<array{label: string, minutes: int, date?: string}>
 */
function getHabitBars(string $view, DateTimeImmutable $focusDate, array $calendarDates, array $minutesByDate): array
{
    $bars = [];

    if ($view === 'month') {
        $month = $focusDate->format('Y-m');
        foreach (array_chunk($calendarDates, 7) as $weekIndex => $weekDates) {
            $weekMinutes = 0;
            foreach ($weekDates as $day) {
                // Hanya hitung hari yang masuk bulan ini.
                if ($day->format('Y-m') === $month) {
                    $weekMinutes += $minutesByDate[$day->format('Y-m-d')] ?? 0;
                }
            }
            $bars[] = ['label' => 'Week ' . ($weekIndex + 1), 'minutes' => $weekMinutes];
        }
        return $bars;
    }

    $days = $view === 'day' ? [$focusDate] : $calendarDates;
    foreach ($days as $day) {
        $date = $day->format('Y-m-d');
        $bars[] = [
            'label' => $view === 'day' ? 'Today' : $day->format('D'),
            'date' => $date,
            'minutes' => $minutesByDate[$date] ?? 0,
        ];
    }
    return $bars;
}

/**
 * Rencana belajar yang akan datang (mulai dari sekarang).
 * 'timestamp' diisi kalau rencana punya jam (dipakai untuk countdown live di JavaScript).
 */
function getUpcomingPlans(mysqli $conn, int $userId, DateTimeImmutable $today): array
{
    $query = $conn->prepare(
        "SELECT title, plan_date, plan_time,
                IF(plan_time IS NULL, NULL, UNIX_TIMESTAMP(CONCAT(plan_date, ' ', plan_time)))
         FROM study_plans
         WHERE user_id = ? AND (
            plan_date > CURDATE()
            OR (plan_date = CURDATE() AND (plan_time IS NULL OR plan_time >= CURTIME()))
         )
         ORDER BY plan_date, plan_time IS NULL, plan_time, id"
    );
    $query->bind_param('i', $userId);
    $query->execute();
    $query->bind_result($title, $date, $time, $timestamp);

    $plans = [];
    while ($query->fetch()) {
        $plans[] = [
            'title' => $title,
            'date' => $date,
            'time' => $time,
            'timestamp' => $timestamp === null ? null : (int) $timestamp,
            'days_until' => (int) $today->diff(parseDate($date))->days,
        ];
    }
    $query->close();

    return $plans;
}

/**
 * Tanggal tujuan tombol ‹ (sebelumnya) dan › (berikutnya) di kalender.
 *
 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
 */
function getPreviousAndNextDate(string $view, DateTimeImmutable $focusDate): array
{
    if ($view === 'day') {
        return [$focusDate->modify('-1 day'), $focusDate->modify('+1 day')];
    }
    if ($view === 'week') {
        return [$focusDate->modify('-7 days'), $focusDate->modify('+7 days')];
    }

    // Tampilan bulan: pindah ke tanggal yang sama di bulan sebelumnya/berikutnya.
    // Kalau tanggalnya tidak ada (contoh 31 → Februari), pakai tanggal terakhir bulan itu.
    $dayOfMonth = (int) $focusDate->format('j');
    $result = [];
    foreach (['previous', 'next'] as $direction) {
        $month = $focusDate->modify('first day of ' . $direction . ' month');
        $result[] = $month->setDate(
            (int) $month->format('Y'),
            (int) $month->format('n'),
            min($dayOfMonth, (int) $month->format('t'))
        );
    }
    return $result;
}

/** Teks periode di atas kalender, contoh: "November 2026" atau "Nov 9 – Nov 15, 2026". */
function getPeriodLabel(string $view, DateTimeImmutable $start, DateTimeImmutable $end, DateTimeImmutable $focusDate): string
{
    if ($view === 'day') {
        return $focusDate->format('l, F j, Y');
    }
    if ($view === 'week') {
        return $start->format('M j') . ' – ' . $end->modify('-1 day')->format('M j, Y');
    }
    return $focusDate->format('F Y');
}
