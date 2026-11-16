<?php
/**
 * API LOGIN / DAFTAR / LOGOUT UNTUK APLIKASI ANDROID
 *
 * Request (POST, JSON):
 *   { "action": "login",    "email": "...", "password": "..." }
 *   { "action": "register", "name": "...", "email": "...", "password": "..." }
 *   { "action": "logout" }   ← wajib kirim header Authorization: Bearer <token>
 *
 * Response login/register berhasil:
 *   { "token": "<64 karakter>", "user": { "id": 1, "name": "...", "email": "..." } }
 * Gagal:
 *   { "error": "..." }
 */
require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');

$input = readJsonInput() ?? [];
$action = $input['action'] ?? '';

try {
    /* ---------- LOGIN ---------- */
    if ($action === 'login') {
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        $query = $conn->prepare('SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1');
        $query->bind_param('s', $email);
        $query->execute();
        $query->bind_result($userId, $name, $storedEmail, $passwordHash);
        $userFound = $query->fetch();
        $query->close();

        if (!$userFound || !is_string($passwordHash) || !password_verify($password, $passwordHash)) {
            jsonResponse(401, ['error' => 'Incorrect email or password.']);
        }

        jsonResponse(200, [
            'token' => createApiToken($conn, (int) $userId),
            'user' => ['id' => (int) $userId, 'name' => $name, 'email' => $storedEmail],
        ]);
    }

    /* ---------- DAFTAR ---------- */
    if ($action === 'register') {
        // Aturan sama dengan auth/register.php (website).
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($name === '' || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            jsonResponse(422, ['error' => 'Please enter your name, a valid email, and password.']);
        }

        $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();
        $emailTaken = $check->num_rows > 0;
        $check->close();
        if ($emailTaken) {
            jsonResponse(409, ['error' => 'Email is already registered.']);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $conn->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
        $insert->bind_param('sss', $name, $email, $passwordHash);
        $insert->execute();
        $userId = $conn->insert_id;
        $insert->close();

        // Langsung login setelah daftar.
        jsonResponse(201, [
            'token' => createApiToken($conn, (int) $userId),
            'user' => ['id' => (int) $userId, 'name' => $name, 'email' => $email],
        ]);
    }

    /* ---------- LOGOUT ---------- */
    if ($action === 'logout') {
        $token = getRequestToken();
        if ($token !== '') {
            $hash = hash('sha256', $token);
            $delete = $conn->prepare('DELETE FROM api_tokens WHERE token_hash = ?');
            $delete->bind_param('s', $hash);
            $delete->execute();
            $delete->close();
        }
        jsonResponse(200, ['message' => 'You have been logged out.']);
    }

    jsonResponse(422, ['error' => 'Choose a valid action.']);
} catch (mysqli_sql_exception $e) {
    error_log('Mobile auth failed: ' . $e->getMessage());
    jsonResponse(500, ['error' => 'Something went wrong. Please try again.']);
}
