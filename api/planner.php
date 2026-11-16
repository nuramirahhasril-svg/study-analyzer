<?php
/**
 * API PLANNER — dipanggil oleh assets/js/planner.js lewat fetch().
 *
 * Request (POST, JSON):
 *   { "action": "create", "title": "...", "date": "2026-11-13", "time": "14:30", "notes": "..." }
 *   { "action": "delete", "id": 12 }
 *
 * Response (JSON):
 *   berhasil → { "message": "..." }
 *   gagal    → { "error": "..." }
 *
 * Logikanya ada di includes/planner_actions.php (dipakai juga oleh aplikasi Android).
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/planner_actions.php';

checkApiRequest('Please log in to manage your study planner.');

$input = readJsonInput();
if ($input === null) {
    jsonResponse(400, ['error' => 'The planner request was not valid.']);
}

handlePlannerAction($conn, currentUserId(), $input);
