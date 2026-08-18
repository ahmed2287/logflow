<?php
declare(strict_types=1);

/**
 * Bootstrap: paths, config, session, shared helpers.
 */

define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('VIEWS_PATH', BASE_PATH . '/views');
define('USERS_FILE', DATA_PATH . '/users.json');
define('CONFIG_FILE', DATA_PATH . '/config.json');
define('AUDIT_FILE', DATA_PATH . '/audit.log');

mb_internal_encoding('UTF-8');
date_default_timezone_set(getenv('APP_TZ') ?: 'Africa/Cairo');

if (!is_dir(DATA_PATH)) {
    mkdir(DATA_PATH, 0750, true);
}

/* ---------------------------------------------------------------- config */

const DEFAULT_CONFIG = [
    'sources'      => [],            // [{name: "...", path: "/abs/dir"}, ...]
    'patterns'     => ['*.log', '*.txt'],
    'recursive'    => true,
    'tail_lines'   => 500,
    'max_view_mb'  => 20,
];

function config_load(): array
{
    if (!is_file(CONFIG_FILE)) {
        return DEFAULT_CONFIG;
    }
    $raw = json_decode((string)file_get_contents(CONFIG_FILE), true);
    if (!is_array($raw)) {
        return DEFAULT_CONFIG;
    }
    $config = array_merge(DEFAULT_CONFIG, $raw);
    // Migrate the pre-multi-source shape: a single log_dir string becomes one
    // named source, so existing installs keep working untouched.
    if (!$config['sources'] && !empty($raw['log_dir'])) {
        $config['sources'] = [[
            'name' => basename((string)$raw['log_dir']) ?: 'اللوجات',
            'path' => (string)$raw['log_dir'],
        ]];
    }
    return $config;
}

function config_save(array $config): bool
{
    $config = array_merge(DEFAULT_CONFIG, $config);
    return json_write(CONFIG_FILE, $config);
}

function config(string $key, mixed $default = null): mixed
{
    static $cache = null;
    if ($cache === null) {
        $cache = config_load();
    }
    return $cache[$key] ?? $default;
}

/* ------------------------------------------------------------ json store */

/** Atomic JSON write with an exclusive lock, so concurrent requests can't tear the file. */
function json_write(string $file, mixed $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        return false;
    }
    @chmod($tmp, 0640);
    return rename($tmp, $file);
}

function json_read(string $file, array $default = []): array
{
    if (!is_file($file)) {
        return $default;
    }
    $raw = json_decode((string)file_get_contents($file), true);
    return is_array($raw) ? $raw : $default;
}

/* --------------------------------------------------------------- session */

function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);
    session_name('almasrylog_sid');
    session_start();
}

/* --------------------------------------------------------------- helpers */

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 302);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_take(): array
{
    $out = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $out;
}

function client_ip(): string
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        $value = $_SERVER[$key] ?? '';
        if ($value !== '') {
            return trim(explode(',', $value)[0]);
        }
    }
    return '-';
}

function human_bytes(int|float $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return ($i === 0 ? (string)(int)$bytes : number_format($bytes, 1)) . ' ' . $units[$i];
}

function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require VIEWS_PATH . '/' . $name . '.php';
}

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/jobs.php';
require_once __DIR__ . '/sysinfo.php';
require_once BASE_PATH . '/views/_helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logs.php';
