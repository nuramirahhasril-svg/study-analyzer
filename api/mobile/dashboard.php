<?php
/**
 * API DASHBOARD UNTUK APLIKASI ANDROID
 *
 * Request: GET (dengan header Authorization: Bearer <token>)
 *
 * Response: semua data untuk layar Home, Timer, dan Progress sekaligus:
 * {
 *   "user": {...},
 *   "daily_goal": 120, "default_session": 25,
 *   "today_minutes": 45, "streak_days": 3, "weekly_average": 30,
 *   "suggestion": { "minutes": 25, "reason": "..." },
 *   "active_session": { "id", "subject", "planned_seconds", "elapsed_seconds", "status" } | null,
 *   "recent_sessions": [ { "subject", "minutes", "ended_at" } ],
 *   "last_7_days": [ { "date": "2026-11-13", "label": "Fri", "minutes": 45 } ]
 * }
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/planner_data.php';

requireMethod('GET');
$user = requireApiUser($conn);
$userId = $user['id'];

try {
    $today = getToday($conn);
    $preferences = getStudyPreferences($conn, $userId);
    $todayMinutes = getMinutesStudiedToday($conn, $userId);
    $weeklyAverage = getWeeklyAverageMinutes($conn, $userId);

    // Menit belajar 7 hari terakhir (untuk grafik di layar Progress).
    $weekStart = $today->modify('-6 days');
    $minutesByDate = getStudyMinutesByDate($conn, $userId, $weekStart, $today->modify('+1 day'));
    $lastSevenDays = [];
    foreach (getDatesBetween($weekStart, $today->modify('+1 day')) as $day) {
        $date = $day->format('Y-m-d');
        $lastSevenDays[] = [
            'date' => $date,
            'label' => $day->format('D'),
            'minutes' => $minutesByDate[$date] ?? 0,
        ];
    }

    jsonResponse(200, [
        'user' => $user,
        'today' => $today->format('Y-m-d'),
        'daily_goal' => $preferences['daily_goal'],
        'default_session' => $preferences['default_session'],
        'today_minutes' => $todayMinutes,
        'streak_days' => getStudyStreak($conn, $userId, $today),
        'weekly_average' => $weeklyAverage,
        'suggestion' => getTodaySuggestion($conn, $userId, $preferences, $todayMinutes, $weeklyAverage),
        'active_session' => getActiveSession($conn, $userId),
        'recent_sessions' => getRecentSessions($conn, $userId, 10),
        'last_7_days' => $lastSevenDays,
    ]);
} catch (mysqli_sql_exception $e) {
    error_log('Mobile dashboard failed: ' . $e->getMessage());
    jsonResponse(500, ['error' => 'Your study data could not be loaded. Please try again.']);
}
