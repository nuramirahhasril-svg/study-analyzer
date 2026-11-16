<?php
/**
 * FILE PEMBUKA UNTUK API APLIKASI ANDROID (folder api/mobile/)
 *
 * Bedanya dengan website:
 *   - Website login pakai session (cookie) + token CSRF.
 *   - Aplikasi login pakai TOKEN: setelah login, aplikasi menyimpan token
 *     lalu mengirimnya di setiap request lewat header:
 *         Authorization: Bearer <token>
 *     (atau X-Auth-Token: <token>, untuk server yang membuang header Authorization).
 *
 * Di database hanya disimpan hash SHA-256 dari token (tabel api_tokens),
 * jadi kalau database bocor, tokennya tetap tidak bisa dipakai.
 */
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/study_stats.php';

/** Ambil token dari header request. Hasilnya '' kalau tidak ada / formatnya salah. */
function getRequestToken(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    // Apache (termasuk XAMPP) sering tidak meneruskan header Authorization ke $_SERVER.
    // Kalau begitu, baca langsung dari daftar header request.
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }
    if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $match) === 1) {
        return strtolower($match[1]);
    }

    $fallback = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    return preg_match('/^[a-f0-9]{64}$/i', $fallback) === 1 ? strtolower($fallback) : '';
}

/** Buat token baru untuk user, simpan hash-nya, lalu kembalikan token aslinya (hanya sekali ini). */
function createApiToken(mysqli $conn, int $userId): string
{
    $token = bin2hex(random_bytes(32)); // 64 karakter hex
    $hash = hash('sha256', $token);

    $insert = $conn->prepare('INSERT INTO api_tokens (user_id, token_hash) VALUES (?, ?)');
    $insert->bind_param('is', $userId, $hash);
    $insert->execute();
    $insert->close();

    return $token;
}

/**
 * Pakai di awal endpoint yang wajib login.
 * Kalau token valid → kembalikan data user. Kalau tidak → balas 401 lalu berhenti.
 *
 * @return array{id: int, name: string, email: string}
 */
function requireApiUser(mysqli $conn): array
{
    $token = getRequestToken();
    if ($token === '') {
        jsonResponse(401, ['error' => 'Please log in again.']);
    }

    $hash = hash('sha256', $token);
    $query = $conn->prepare(
        'SELECT users.id, users.name, users.email
         FROM api_tokens
         JOIN users ON users.id = api_tokens.user_id
         WHERE api_tokens.token_hash = ?
         LIMIT 1'
    );
    $query->bind_param('s', $hash);
    $query->execute();
    $query->bind_result($userId, $name, $email);
    $found = $query->fetch();
    $query->close();

    if (!$found) {
        jsonResponse(401, ['error' => 'Your login has expired. Please log in again.']);
    }

    $touch = $conn->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?');
    $touch->bind_param('s', $hash);
    $touch->execute();
    $touch->close();

    return ['id' => (int) $userId, 'name' => $name, 'email' => $email];
}

/** Hentikan request kalau method-nya bukan yang diharapkan ('GET' atau 'POST'). */
function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        jsonResponse(405, ['error' => 'This action requires a ' . $method . ' request.']);
    }
}
