<?php
declare(strict_types=1);

// Small helper functions used everywhere: settings, safe output, forms, login, messages.

/** Read a setting: real environment variable first, then the .env file, then the default. Blank = not set. */
function env(string $key, ?string $default = null): ?string
{
    static $fromFile = null;
    if ($fromFile === null) {
        $fromFile = [];
        $path = ROOT . '/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $fromFile[trim($k)] = trim(trim($v), "\"'");
            }
        }
    }
    $real = getenv($key);
    if ($real !== false && $real !== '') {
        return $real;
    }
    if (isset($fromFile[$key]) && $fromFile[$key] !== '') {
        return $fromFile[$key];
    }
    return $default;
}

function env_bool(string $key, bool $default): bool
{
    $v = env($key);
    return $v === null ? $default : in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
}

function use_simulator(): bool
{
    return env_bool('USE_SIMULATOR', true);
}

// ---------- output ----------
function e(?string $text): string          // always use this when printing user data into HTML
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function money($amount): string
{
    return number_format((float) $amount, 2);
}

// ---------- requests and redirects ----------
function url(string $page = 'checkout', array $query = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $query);
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function query_param(string $key): string
{
    return trim((string) ($_GET[$key] ?? ''));
}

// ---------- session, messages, form protection ----------
function ensure_session(): void
{
    if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function flash(string $type, string $message): void   // type: 'ok' or 'error'
{
    ensure_session();
    $_SESSION['flash'][] = [$type, $message];
}

function take_flashes(): array
{
    ensure_session();
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

function csrf_field(): string      // put inside every <form method="post">
{
    ensure_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function csrf_check(): void        // call first in every page that handles a POST
{
    ensure_session();
    $sent = (string) ($_POST['csrf'] ?? '');
    if ($sent === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $sent)) {
        http_response_code(400);
        exit('This form has expired. Go back, refresh the page and try again.');
    }
}

// ---------- staff login ----------
function current_user(): ?array
{
    ensure_session();
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (current_user() === null) {
        redirect(url('login'));
    }
}

// ---------- fixed lists ----------
const INCIDENT_TYPES = [
    'UNAUTHORIZED_CHARGE' => 'Charged for items not purchased',
    'MISSING_RECORD'      => 'Payment record missing',
    'DUPLICATE_CHARGE'    => 'Charged twice',
    'OTHER'               => 'Other',
];
const STATUSES = ['SUCCESS', 'PENDING', 'FAILED', 'CANCELLED', 'TIMEOUT', 'FLAGGED'];
