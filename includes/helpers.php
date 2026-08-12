<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(array $config): string
{
    $configured = rtrim((string) ($config['app']['base_url'] ?? ''), '/');
    if ($configured !== '') {
        return $configured;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // Normalize when called from /admin, /api, /p, /d, /results, /install
    $script = preg_replace('#/(admin|api|p|d|results|install)(/.*)?$#', '', $script) ?? $script;
    $script = rtrim($script, '/');

    return $scheme . '://' . $host . $script;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function random_token(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function ip_hash(): string
{
    return hash('sha256', client_ip() . '|hcs-vote');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = random_token(32);
    }
    return $_SESSION['csrf'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function setting_get(PDO $pdo, string $key, ?string $default = null): ?string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string) $row['setting_value'] : $default;
}

function setting_set(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function current_election(PDO $pdo): ?array
{
    $stmt = $pdo->query('SELECT * FROM elections ORDER BY id DESC LIMIT 1');
    $row = $stmt->fetch();
    return $row ?: null;
}

function ensure_voter_cookie(): string
{
    $name = 'hcs_voter';
    if (!empty($_COOKIE[$name]) && preg_match('/^[a-f0-9]{32,64}$/', $_COOKIE[$name])) {
        return $_COOKIE[$name];
    }
    $token = random_token(16);
    setcookie($name, $token, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[$name] = $token;
    return $token;
}

function photo_url(array $config, string $photo): string
{
    if ($photo === '') {
        return base_url($config) . '/assets/css/placeholder.svg';
    }
    if (preg_match('#^https?://#i', $photo)) {
        return $photo;
    }
    return base_url($config) . '/assets/uploads/' . ltrim($photo, '/');
}

function upload_photo(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Photo upload failed.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, or WEBP photos are allowed.');
    }
    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException('Photo must be under 3 MB.');
    }

    $dir = dirname(__DIR__) . '/assets/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded photo.');
    }
    return $name;
}

function default_positions(): array
{
    return [
        'Head Boy',
        'Head Girl',
        'Deputy Head Boy',
        'Deputy Head Girl',
        'Sports Captain (Senior Wing)',
        'Sports Captain (Junior Wing)',
        'Cultural Captain (Senior Wing)',
        'Cultural Captain (Junior Wing)',
        'Assembly Incharge (Senior Wing)',
        'Assembly Incharge (Junior Wing)',
        'Discipline Incharge (Senior Wing)',
        'Discipline Incharge (Junior Wing)',
        'Class Captain 10 (L1)',
        'Class Captain 10 (L2)',
        'Class Captain 9 (CHD)',
        'Class Captain 9 (CHN)',
        'Class Captain (Class 8)',
        'Class Captain (Class 7)',
        'Class Captain (Class 6)',
        'Class Captain (Class 5)',
    ];
}
