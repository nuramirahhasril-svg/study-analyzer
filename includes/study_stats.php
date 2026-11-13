<?php
/**
 * STATISTIK BELAJAR
 *
 * Kumpulan fungsi untuk mengambil data belajar user dari database:
 * target harian, menit belajar hari ini, streak, rata-rata 7 hari,
 * saran durasi hari ini, sesi yang sedang berjalan, dan riwayat sesi.
 *
 * Dipakai oleh home.php, timer.php, dan progress.php.
 */

/**
 * Pengaturan belajar user (target harian & durasi sesi default).
 * Kalau user belum punya pengaturan, otomatis dibuatkan dengan nilai default.
 *
 * @return array{daily_goal: int, default_session: int}
 */
function getStudyPreferences(mysqli $conn, int $userId): array
{
    // INSERT IGNORE = buat baris baru hanya kalau belum ada.
    $insert = $conn->prepare('INSERT IGNORE INTO study_preferences (user_id) VALUES (?)');
    $insert->bind_param('i', $userId);
    $insert->execute();
    $insert->close();

    $query = $conn->prepare(
        'SELECT daily_goal_minutes, default_session_minutes
         FROM study_preferences WHERE user_id = ?'
    );
    $query->bind_param('i', $userId);
    $query->execute();
    $query->bind_result($dailyGoal, $defaultSession);
    $query->fetch();
    $query->close();

    return [
        'daily_goal' => (int) $dailyGoal,
        'default_session' => (int) $defaultSession,
    ];
}

/** Total menit belajar yang sudah selesai hari ini. */
function getMinutesStudiedToday(mysqli $conn, int $userId): int
{
    $query = $conn->prepare(
        "SELECT COALESCE(SUM(elapsed_seconds), 0)
         FROM study_sessions
         WHERE user_id = ? AND status = 'completed'
           AND DATE(ended_at) = CURDATE()"
    );
    $query->bind_param('i', $userId);
    $query->execute();
    $query->bind_result($seconds);
    $query->fetch();
    $query->close();

    return (int) floor($seconds / 60);
}

/**
 * Streak = berapa hari berturut-turut user belajar.
 * Kalau hari ini belum belajar, streak dihitung mulai dari kemarin
 * (jadi streak tidak langsung hilang di pagi hari).
 */
function getStudyStreak(mysqli $conn, int $userId, DateTimeImmutable $today): int
{
    // Ambil semua tanggal (maks. 1 tahun terakhir) di mana user menyelesaikan sesi.
    $query = $conn->prepare(
        "SELECT DATE(ended_at) AS study_date
         FROM study_sessions
         WHERE user_id = ? AND status = 'completed'
           AND ended_at >= DATE_SUB(CURDATE(), INTERVAL 366 DAY)
         GROUP BY DATE(ended_at)
         ORDER BY study_date DESC"
    );
    $query->bind_param('i', $userId);
    $query->execute();
    $query->bind_result($studyDate);
    $studyDates = [];
    while ($query->fetch()) {
        $studyDates[] = $studyDate;
    }
    $query->close();

    $day = $today;
    if ($studyDates && $studyDates[0] !== $day->format('Y-m-d')) {
        $day = $day->modify('-1 day');
    }

    // Mundur satu hari demi satu hari selama tanggal itu ada di daftar.
    $streak = 0;
    while (in_array($day->format('Y-m-d'), $studyDates, true)) {
        $streak++;
        $day = $day->modify('-1 day');
    }
    return $streak;
}

/** Rata-rata menit belajar per hari selama 7 hari terakhir (termasuk hari ini). */
function getWeeklyAverageMinutes(mysqli $conn, int $userId): int
{
    $query = $conn->prepare(
        "SELECT COALESCE(SUM(elapsed_seconds), 0)
         FROM study_sessions
         WHERE user_id = ? AND status = 'completed'
           AND ended_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)"
    );
    $query->bind_param('i', $userId);
    $query->execute();
    $query->bind_result($seconds);
    $query->fetch();
    $query->close();

    return (int) floor($seconds / 7 / 60);
}

/**
 * Saran durasi belajar untuk hari ini + alasannya.
 * Saran juga disimpan ke tabel study_recommendations (satu baris per user per hari).
 *
 * @return array{minutes: int, reason: string}
 */
function getTodaySuggestion(mysqli $conn, int $userId, array $preferences, int $todayMinutes, int $weeklyAverage): array
{
    $remainingMinutes = max(0, $preferences['daily_goal'] - $todayMinutes);
    $minutes = min($preferences['default_session'], $remainingMinutes);

    if ($minutes === 0) {
        $reason = 'You have reached your daily study goal. Take a well-earned break.';
    } elseif ($weeklyAverage === 0) {
        $reason = 'Start with a focused ' . $minutes . '-minute session and build a steady routine.';
    } else {
        $reason = 'Your 7-day average is ' . $weeklyAverage . ' minutes a day. Try ' .
            $minutes . ' focused minutes today.';
    }

    $save = $conn->prepare(
        'INSERT INTO study_recommendations
            (user_id, recommendation_date, recommended_minutes, reason)
         VALUES (?, CURDATE(), ?, ?)
         ON DUPLICATE KEY UPDATE
            recommended_minutes = VALUES(recommended_minutes),
            reason = VALUES(reason)'
    );
    $save->bind_param('iis', $userId, $minutes, $reason);
    $save->execute();
    $save->close();

    return ['minutes' => $minutes, 'reason' => $reason];
}

/**
 * Hitung total detik belajar sebuah sesi sampai detik ini.
 *
 * @param int      $savedSeconds   detik yang sudah tersimpan di kolom elapsed_seconds
 * @param int|null $runningSeconds detik sejak sesi terakhir di-start/resume (null kalau sedang pause)
 */
function countStudiedSeconds(int $savedSeconds, ?int $runningSeconds): int
{
    return $savedSeconds + max(0, (int) $runningSeconds);
}

/**
 * Sesi timer yang sedang berjalan atau di-pause (null kalau tidak ada).
 *
 * @return array{id: int, subject: string, planned_seconds: int, elapsed_seconds: int, status: string}|null
 */
function getActiveSession(mysqli $conn, int $userId): ?array
{
    // PENTING: lama sesi berjalan dihitung di MySQL (TIMESTAMPDIFF ... NOW()), bukan di PHP.
    // running_since disimpan memakai jam MySQL, sedangkan zona waktu PHP bisa berbeda.
    // Kalau dihitung pakai jam PHP, timer bisa langsung habis (00:00) saat halaman di-reload.
    $query = $conn->prepare(
        "SELECT id, subject, planned_seconds, elapsed_seconds, status,
                TIMESTAMPDIFF(SECOND, running_since, NOW()) AS running_seconds
         FROM study_sessions
         WHERE user_id = ? AND status IN ('running', 'paused')
         ORDER BY id DESC LIMIT 1"
    );
    $query->bind_param('i', $userId);
    $query->execute();
    $query->bind_result($id, $subject, $plannedSeconds, $savedSeconds, $status, $runningSeconds);
    $found = $query->fetch();
    $query->close();

    if (!$found) {
        return null;
    }

    $studiedSeconds = countStudiedSeconds(
        (int) $savedSeconds,
        $status === 'running' ? $runningSeconds : null
    );

    return [
        'id' => (int) $id,
        'subject' => $subject,
        'planned_seconds' => (int) $plannedSeconds,
        // Tidak boleh lebih dari durasi yang direncanakan.
        'elapsed_seconds' => min($studiedSeconds, (int) $plannedSeconds),
        'status' => $status,
    ];
}

/**
 * Sesi belajar terakhir yang sudah selesai.
 *
 * @return list<array{subject: string, minutes: int, ended_at: string|null}>
 */
function getRecentSessions(mysqli $conn, int $userId, int $limit = 5): array
{
    $query = $conn->prepare(
        "SELECT subject, elapsed_seconds, ended_at
         FROM study_sessions
         WHERE user_id = ? AND status = 'completed'
         ORDER BY ended_at DESC LIMIT ?"
    );
    $query->bind_param('ii', $userId, $limit);
    $query->execute();
    $query->bind_result($subject, $seconds, $endedAt);

    $sessions = [];
    while ($query->fetch()) {
        $sessions[] = [
            'subject' => $subject,
            'minutes' => (int) floor($seconds / 60),
            'ended_at' => $endedAt,
        ];
    }
    $query->close();

    return $sessions;
}
