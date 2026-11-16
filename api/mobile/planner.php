<?php
/**
 * API PLANNER UNTUK APLIKASI ANDROID
 *
 * GET  ?from=2026-11-01&to=2026-12-01   (tanggal "to" tidak ikut, maks. 100 hari)
 *   → { "plans": [ { "id", "title", "notes", "date", "time" } ],
 *       "minutes_by_date": { "2026-11-13": 45 } }
 *
 * POST (JSON) — sama dengan api/planner.php:
 *   { "action": "create", "title": "...", "date": "2026-11-13", "time": "14:30", "notes": "..." }
 *   { "action": "delete", "id": 12 }
 *
 * Semua request wajib header Authorization: Bearer <token>.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/planner_data.php';
require_once __DIR__ . '/../../includes/planner_actions.php';

$user = requireApiUser($conn);
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = readJsonInput();
    if ($input === null) {
        jsonResponse(400, ['error' => 'The planner request was not valid.']);
    }
    handlePlannerAction($conn, $userId, $input);
}

requireMethod('GET');

$from = parseDate((string) ($_GET['from'] ?? ''));
$to = parseDate((string) ($_GET['to'] ?? ''));
if ($from === null || $to === null || $to <= $from || $from->diff($to)->days > 100) {
    jsonResponse(422, ['error' => 'Choose a valid date range (up to 100 days).']);
}

try {
    $plans = [];
    foreach (getPlansByDate($conn, $userId, $from, $to) as $plansOfDay) {
        foreach ($plansOfDay as $plan) {
            // "14:30:00" → "14:30" supaya gampang dipakai di aplikasi.
            $plan['time'] = $plan['time'] === null ? null : substr($plan['time'], 0, 5);
            $plans[] = $plan;
        }
    }

    jsonResponse(200, [
        'plans' => $plans,
        // (object) supaya hasilnya tetap {} (bukan []) kalau kosong.
        'minutes_by_date' => (object) getStudyMinutesByDate($conn, $userId, $from, $to),
    ]);
} catch (mysqli_sql_exception $e) {
    error_log('Mobile planner failed: ' . $e->getMessage());
    jsonResponse(500, ['error' => 'Your planner could not be loaded. Please try again.']);
}
