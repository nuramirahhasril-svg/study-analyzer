<?php
/**
 * API TIMER UNTUK APLIKASI ANDROID
 *
 * Request (POST, JSON, dengan header Authorization: Bearer <token>) — sama dengan api/timer.php:
 *   { "action": "start", "subject": "Biology", "duration_seconds": 1500 }
 *   { "action": "pause" } | { "action": "resume" } | { "action": "finish" } | { "action": "cancel" }
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/timer_actions.php';

requireMethod('POST');
$user = requireApiUser($conn);

handleTimerAction($conn, $user['id'], readJsonInput() ?? []);
