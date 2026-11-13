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
 */
require_once __DIR__ . '/../includes/init.php';

checkApiRequest('Please log in to manage your study planner.');

$input = readJsonInput();
if ($input === null) {
    jsonResponse(400, ['error' => 'The planner request was not valid.']);
}

$action = $input['action'] ?? '';
$userId = currentUserId();

try {
    /* ---------- TAMBAH RENCANA ---------- */
    if ($action === 'create') {
        $title = trim((string) ($input['title'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));
        $date = (string) ($input['date'] ?? '');
        $time = trim((string) ($input['time'] ?? ''));

        // Validasi input. Regex /u menghitung karakter, bukan byte (aman untuk emoji/huruf non-latin).
        $validTitle = preg_match('/^.{1,120}$/us', $title) === 1;
        $validNotes = preg_match('/^.{0,500}$/us', $notes) === 1;
        $validDate = parseDate($date) !== null;
        $validTime = $time === '' || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1; // format HH:MM

        if (!$validTitle || !$validNotes || !$validDate || !$validTime) {
            jsonResponse(422, ['error' => 'Add a title, a valid date, and optional notes up to 500 characters.']);
        }

        // Rencana hanya boleh dari hari ini sampai 2 tahun ke depan.
        $today = getToday($conn);
        if ($date < $today->format('Y-m-d') || $date > $today->modify('+2 years')->format('Y-m-d')) {
            jsonResponse(422, ['error' => 'Plans can be scheduled from today up to two years ahead.']);
        }

        $planTime = $time === '' ? null : $time . ':00'; // null = sepanjang hari
        $insert = $conn->prepare(
            'INSERT INTO study_plans (user_id, title, notes, plan_date, plan_time)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insert->bind_param('issss', $userId, $title, $notes, $date, $planTime);
        $insert->execute();
        $planId = $conn->insert_id;
        $insert->close();

        jsonResponse(201, ['message' => 'Your study plan has been saved.', 'id' => $planId]);
    }

    /* ---------- HAPUS RENCANA ---------- */
    if ($action === 'delete') {
        $planId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        if ($planId === false || $planId < 1) {
            jsonResponse(422, ['error' => 'Choose a valid study plan to remove.']);
        }

        // "AND user_id = ?" memastikan user hanya bisa menghapus rencana miliknya sendiri.
        $delete = $conn->prepare('DELETE FROM study_plans WHERE id = ? AND user_id = ?');
        $delete->bind_param('ii', $planId, $userId);
        $delete->execute();
        $deleted = $delete->affected_rows === 1;
        $delete->close();

        if (!$deleted) {
            jsonResponse(404, ['error' => 'That study plan could not be found.']);
        }
        jsonResponse(200, ['message' => 'The study plan has been removed.']);
    }

    jsonResponse(422, ['error' => 'Choose a valid planner action.']);
} catch (mysqli_sql_exception $e) {
    error_log('Study planner action failed: ' . $e->getMessage());
    jsonResponse(500, ['error' => 'The planner could not be updated. Please try again.']);
}
