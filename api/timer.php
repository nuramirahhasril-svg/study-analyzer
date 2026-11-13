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
 * Cara menyimpan waktu di tabel study_sessions:
 *   elapsed_seconds → detik yang sudah "ditabung" (diperbarui saat pause/finish)
 *   running_since   → kapan sesi terakhir di-start/resume (NULL kalau sedang pause)
 *   Total waktu belajar = elapsed_seconds + (sekarang - running_since)
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/study_stats.php';

checkApiRequest('Please log in to use the study timer.');

$input = readJsonInput() ?? [];
$action = $input['action'] ?? '';
$userId = currentUserId();

/** Batalkan transaksi database lalu kirim pesan error. */
function rollbackWithError(mysqli $conn, int $status, string $error): void
{
    $conn->rollback();
    jsonResponse($status, ['error' => $error]);
}

try {
    // Transaksi: semua perubahan disimpan sekaligus di akhir (commit),
    // atau dibatalkan semua kalau ada yang gagal (rollback).
    $conn->begin_transaction();

    // Kunci baris user (FOR UPDATE) supaya dua klik yang bersamaan tidak membuat dua sesi.
    $userLock = $conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
    $userLock->bind_param('i', $userId);
    $userLock->execute();
    $userLock->store_result();
    $userExists = $userLock->num_rows === 1;
    $userLock->close();
    if (!$userExists) {
        rollbackWithError($conn, 401, 'Your account could not be found. Please log in again.');
    }

    /* ---------- MULAI SESI BARU ---------- */
    if ($action === 'start') {
        $subject = trim((string) ($input['subject'] ?? ''));
        $plannedSeconds = filter_var($input['duration_seconds'] ?? null, FILTER_VALIDATE_INT);

        // Durasi 1 detik sampai 120 menit (7200 detik).
        if (preg_match('/^.{1,100}$/us', $subject) !== 1 ||
            $plannedSeconds === false || $plannedSeconds < 1 || $plannedSeconds > 7200) {
            rollbackWithError($conn, 422, 'Enter a subject and a duration between 1 and 120 minutes.');
        }

        // Hanya boleh ada satu sesi aktif.
        $active = $conn->prepare(
            "SELECT id FROM study_sessions
             WHERE user_id = ? AND status IN ('running', 'paused')
             LIMIT 1 FOR UPDATE"
        );
        $active->bind_param('i', $userId);
        $active->execute();
        $active->store_result();
        $hasActiveSession = $active->num_rows > 0;
        $active->close();
        if ($hasActiveSession) {
            rollbackWithError($conn, 409, 'Finish or resume your current study session first.');
        }

        $insert = $conn->prepare(
            "INSERT INTO study_sessions
                (user_id, subject, planned_seconds, status, started_at, running_since)
             VALUES (?, ?, ?, 'running', NOW(), NOW())"
        );
        $insert->bind_param('isi', $userId, $subject, $plannedSeconds);
        $insert->execute();
        $sessionId = $conn->insert_id;
        $insert->close();
        $conn->commit();

        jsonResponse(200, [
            'message' => 'Your study session has started.',
            'session' => [
                'id' => $sessionId,
                'subject' => $subject,
                'planned_seconds' => $plannedSeconds,
                'elapsed_seconds' => 0,
                'status' => 'running',
            ],
        ]);
    }

    if (!in_array($action, ['pause', 'resume', 'finish', 'cancel'], true)) {
        rollbackWithError($conn, 422, 'Choose a valid timer action.');
    }

    /* ---------- AMBIL SESI YANG SEDANG AKTIF ---------- */
    // Lama sesi berjalan dihitung di MySQL (TIMESTAMPDIFF ... NOW()), sama dengan jam running_since.
    $active = $conn->prepare(
        "SELECT id, planned_seconds, elapsed_seconds, status,
                TIMESTAMPDIFF(SECOND, running_since, NOW()) AS running_seconds
         FROM study_sessions
         WHERE user_id = ? AND status IN ('running', 'paused')
         ORDER BY id DESC LIMIT 1 FOR UPDATE"
    );
    $active->bind_param('i', $userId);
    $active->execute();
    $active->bind_result($sessionId, $plannedSeconds, $savedSeconds, $status, $runningSeconds);
    $found = $active->fetch();
    $active->close();
    if (!$found) {
        rollbackWithError($conn, 404, 'There is no active study session.');
    }

    if ($action === 'pause' && $status !== 'running') {
        rollbackWithError($conn, 409, 'This session is already paused.');
    }
    if ($action === 'resume' && $status !== 'paused') {
        rollbackWithError($conn, 409, 'This session is already running.');
    }

    // Total waktu belajar sampai sekarang...
    $totalSeconds = countStudiedSeconds($savedSeconds, $status === 'running' ? $runningSeconds : null);
    // ...tapi TIDAK BOLEH lebih dari durasi yang direncanakan.
    // Contoh: timer 25 menit, browser ditutup, baru kembali besok → tetap tercatat 25 menit, bukan 20 jam.
    $studiedSeconds = min($totalSeconds, $plannedSeconds);
    // Waktu "lebih" = berapa lama timer sebenarnya sudah habis tapi belum di-finish.
    $overtimeSeconds = $totalSeconds - $studiedSeconds;

    /* ---------- JALANKAN ACTION ---------- */
    if ($action === 'pause') {
        $update = $conn->prepare(
            "UPDATE study_sessions
             SET elapsed_seconds = ?, status = 'paused', running_since = NULL
             WHERE id = ? AND user_id = ?"
        );
        $update->bind_param('iii', $studiedSeconds, $sessionId, $userId);
        $message = 'Your session is paused.';

    } elseif ($action === 'resume') {
        $update = $conn->prepare(
            "UPDATE study_sessions
             SET status = 'running', running_since = NOW()
             WHERE id = ? AND user_id = ?"
        );
        $update->bind_param('ii', $sessionId, $userId);
        $message = 'Your session has resumed.';

    } elseif ($action === 'finish') {
        // ended_at = saat timer benar-benar habis (sekarang dikurangi waktu lebih),
        // supaya sesi tercatat di tanggal yang benar untuk streak & grafik.
        $update = $conn->prepare(
            "UPDATE study_sessions
             SET elapsed_seconds = ?, status = 'completed', running_since = NULL,
                 ended_at = DATE_SUB(NOW(), INTERVAL ? SECOND)
             WHERE id = ? AND user_id = ?"
        );
        $update->bind_param('iiii', $studiedSeconds, $overtimeSeconds, $sessionId, $userId);
        $message = 'Your study session is saved. Nice work!';

    } else { // cancel
        $update = $conn->prepare(
            "UPDATE study_sessions
             SET status = 'cancelled', running_since = NULL, ended_at = NOW()
             WHERE id = ? AND user_id = ?"
        );
        $update->bind_param('ii', $sessionId, $userId);
        $message = 'Your study session was cancelled.';
    }

    $update->execute();
    $update->close();
    $conn->commit();
    jsonResponse(200, ['message' => $message]);
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log('Study timer action failed: ' . $e->getMessage());
    jsonResponse(500, ['error' => 'The timer could not be saved. Please try again.']);
}
