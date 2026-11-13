<?php
/**
 * FUNGSI BANTUAN UMUM
 *
 * Fungsi kecil yang dipakai di banyak file: escape HTML, redirect,
 * cek login, token CSRF, dan balasan JSON untuk API.
 */

/* =========================================================
 * Tampilan (HTML)
 * ========================================================= */

/**
 * Amankan teks sebelum ditampilkan di HTML (mencegah serangan XSS).
 * Selalu pakai e() untuk data dari user/database: <?= e($nama) ?>
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Alamat file CSS/JS/gambar + versi berdasarkan waktu file diubah.
 * Tujuannya supaya browser langsung memakai file terbaru, bukan file lama dari cache.
 * Contoh: asset('assets/css/dashboard.css') → "assets/css/dashboard.css?v=1731480000"
 */
function asset(string $path): string
{
    $fullPath = __DIR__ . '/../' . $path;
    $version = is_file($fullPath) ? filemtime($fullPath) : 0;
    return $path . '?v=' . $version;
}

/** Pindah ke halaman lain lalu hentikan script. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* =========================================================
 * Login & keamanan
 * ========================================================= */

/** Apakah user sudah login? */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['email']);
}

/** Pakai di awal halaman yang wajib login. Kalau belum login → kembali ke halaman login. */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('index.php');
    }
}

/** ID user yang sedang login. */
function currentUserId(): int
{
    return (int) $_SESSION['user_id'];
}

/**
 * Token CSRF: kode rahasia per-session yang ikut dikirim setiap form/fetch.
 * Server mengecek token ini supaya website lain tidak bisa mengirim request atas nama user.
 */
function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Cek apakah token yang dikirim sama dengan token di session. */
function isValidCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/* =========================================================
 * API (file di folder api/ yang dipanggil JavaScript)
 * ========================================================= */

/** Kirim balasan JSON dengan status HTTP tertentu, lalu hentikan script. */
function jsonResponse(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Pemeriksaan standar untuk semua API:
 * 1. user harus login, 2. harus method POST, 3. token CSRF harus benar.
 */
function checkApiRequest(string $notLoggedInMessage): void
{
    if (!isLoggedIn()) {
        jsonResponse(401, ['error' => $notLoggedInMessage]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(405, ['error' => 'This action requires a POST request.']);
    }
    if (!isValidCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        jsonResponse(403, ['error' => 'Your session expired. Refresh the page and try again.']);
    }
}

/** Baca data JSON yang dikirim JavaScript. Hasilnya array, atau null kalau tidak valid. */
function readJsonInput(): ?array
{
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : null;
}

/* =========================================================
 * Tanggal
 * ========================================================= */

/**
 * Tanggal hari ini menurut MySQL.
 * Kita pakai jam MySQL (bukan jam PHP) supaya semua perhitungan tanggal konsisten,
 * karena zona waktu PHP dan MySQL bisa berbeda (contoh: XAMPP default PHP = Europe/Berlin).
 */
function getToday(mysqli $conn): DateTimeImmutable
{
    $result = $conn->query('SELECT CURDATE()');
    $todayDate = $result->fetch_row()[0];
    $result->close();
    return DateTimeImmutable::createFromFormat('!Y-m-d', $todayDate);
}

/** Ubah teks "YYYY-MM-DD" jadi tanggal. Hasilnya null kalau formatnya salah (contoh: 2026-02-31). */
function parseDate(string $text): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
    if ($date === false || $date->format('Y-m-d') !== $text) {
        return null;
    }
    return $date;
}
