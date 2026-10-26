<?php
declare(strict_types=1);

define('PULSAR_ROOT', dirname(__DIR__));
define('PULSAR_VERSION', '1.0.1');

$GLOBALS['pulsar_config'] = require PULSAR_ROOT . '/config.php';

date_default_timezone_set(config('timezone', 'Europe/Madrid'));
mb_internal_encoding('UTF-8');

require PULSAR_ROOT . '/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    $file = PULSAR_ROOT . '/inc/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// ---------------------------------------------------------------------------
// Utilitats generals
// ---------------------------------------------------------------------------

function config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['pulsar_config'][$key] ?? $default;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function storage_path(string $sub = ''): string
{
    $path = PULSAR_ROOT . '/storage' . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    $dir = str_contains(basename($path), '.') ? dirname($path) : $path;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $path;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

// ---------------------------------------------------------------------------
// Sessió
// ---------------------------------------------------------------------------

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name(config('session_name', 'pulsar_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $lifetime = (int) config('session_lifetime', 28800);
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $lifetime) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('La sessió ha caducat. Torna enrere i recarrega la pàgina.');
    }
}

// ---------------------------------------------------------------------------
// Autenticació
// ---------------------------------------------------------------------------

function current_user(): ?string
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (current_user() === null) {
        redirect('login.php');
    }
}

function throttle_file(): string
{
    return storage_path('throttle.json');
}

function throttle_read(): array
{
    $file = throttle_file();
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    return is_array($data) ? $data : [];
}

function throttle_write(array $data): void
{
    file_put_contents(throttle_file(), json_encode($data), LOCK_EX);
}

/** Segons de bloqueig restants per a la IP actual (0 = no bloquejada). */
function login_locked_for(): int
{
    $key = hash('sha256', client_ip());
    $entry = throttle_read()[$key] ?? null;
    if (!$entry || $entry['count'] < (int) config('login_max_attempts', 5)) {
        return 0;
    }
    $remaining = $entry['time'] + (int) config('login_lock_seconds', 900) - time();
    return max(0, $remaining);
}

function attempt_login(string $username, string $password): bool
{
    $users = config('users', []);
    $stored = $users[$username] ?? null;
    $ok = false;

    if (is_string($stored) && $stored !== '') {
        $isHash = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2');
        $ok = $isHash ? password_verify($password, $stored) : hash_equals($stored, $password);
    } else {
        password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'); // temps constant
    }

    $data = throttle_read();
    $key = hash('sha256', client_ip());
    $lock = (int) config('login_lock_seconds', 900);
    foreach ($data as $k => $entry) {
        if (time() - $entry['time'] > $lock) {
            unset($data[$k]);
        }
    }

    if ($ok) {
        unset($data[$key]);
        throttle_write($data);
        session_regenerate_id(true);
        $_SESSION['user'] = $username;
        return true;
    }

    $data[$key] = ['count' => ($data[$key]['count'] ?? 0) + 1, 'time' => time()];
    throttle_write($data);
    usleep(400000);
    return false;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------------
// Informes desats
// ---------------------------------------------------------------------------

function save_report(string $type, array $data): string
{
    cleanup_reports();
    $id = bin2hex(random_bytes(8));
    $data['id'] = $id;
    $data['type'] = $type;
    $data['created_at'] = date('c');
    $data['user'] = current_user();
    file_put_contents(storage_path("reports/{$type}_{$id}.json"), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $id;
}

function load_report(string $type, string $id): ?array
{
    if (!in_array($type, ['seo', 'geo'], true) || !preg_match('/^[a-f0-9]{16}$/', $id)) {
        return null;
    }
    $file = storage_path("reports/{$type}_{$id}.json");
    if (!is_file($file)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

/** @return array<int, array{type:string,id:string,created_at:string,urls:int,score:int}> */
function list_reports(int $limit = 10): array
{
    $files = glob(storage_path('reports') . '/*.json') ?: [];
    usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
    $out = [];
    foreach (array_slice($files, 0, $limit) as $file) {
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            continue;
        }
        $out[] = [
            'type'       => $data['type'],
            'id'         => $data['id'],
            'created_at' => $data['created_at'],
            'urls'       => count($data['results'] ?? []),
            'score'      => (int) ($data['average'] ?? 0),
            'first_url'  => $data['results'][0]['url'] ?? '',
        ];
    }
    return $out;
}

function cleanup_reports(): void
{
    $maxAge = (int) config('report_retention_days', 30) * 86400;
    foreach (glob(storage_path('reports') . '/*.json') ?: [] as $file) {
        if (time() - filemtime($file) > $maxAge) {
            @unlink($file);
        }
    }
}

/** Normalitza el contingut d’un textarea a una llista d’URL úniques i vàlides. */
function parse_url_list(string $raw, int $max): array
{
    $urls = [];
    foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!preg_match('#^https?://#i', $line)) {
            $line = 'https://' . $line;
        }
        $host = (string) parse_url($line, PHP_URL_HOST);
        if (filter_var($line, FILTER_VALIDATE_URL) && str_contains($host, '.')
            && in_array(strtolower((string) parse_url($line, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            $urls[$line] = true;
        }
    }
    return array_slice(array_keys($urls), 0, $max);
}

function score_class(int $score): string
{
    return $score >= 80 ? 'good' : ($score >= 50 ? 'mid' : 'bad');
}

/** Puntuació 0-100 a partir d’una llista de comprovacions ponderades. */
function compute_score(array $checks): int
{
    $total = 0;
    $earned = 0;
    foreach ($checks as $c) {
        if ($c['status'] === 'info') {
            continue;
        }
        $w = $c['weight'] ?? 1;
        $total += $w;
        $earned += $w * ['pass' => 1, 'warn' => 0.5, 'fail' => 0][$c['status']];
    }
    return $total > 0 ? (int) round($earned / $total * 100) : 0;
}
