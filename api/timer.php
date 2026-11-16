<?php
/**
 * API TIMER — dipanggil oleh assets/js/timer.js lewat fetch().
 *
 * Request (POST, JSON):
 *   { "action": "start", "subject": "Biology", "duration_seconds": 1500 }
 *   { "action": "pause" }  |  { "action": "resume" }  |  { "action": "finish" }  |  { "action": "cancel" }
 *
 * Response (JSON):
 *   berhasil → { "message": "..." }   (action "start" juga mengirim "session")
 *   gagal    → { "error": "..." }
 *
 * Logika timernya ada di includes/timer_actions.php (dipakai juga oleh aplikasi Android).
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/study_stats.php';
require_once __DIR__ . '/../includes/timer_actions.php';

checkApiRequest('Please log in to use the study timer.');

handleTimerAction($conn, currentUserId(), readJsonInput() ?? []);
