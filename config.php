<?php
/**
 * QuizArena - configuration, database connection and helper functions.
 * Requires PHP 8.0+ and MySQL / MariaDB.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

date_default_timezone_set('Asia/Kolkata');

// ---- Database settings (change these) ----
const DB_HOST = '127.0.0.1';
const DB_PORT = 3307;          // XAMPP default is 3306. Use the port shown next to MySQL in your XAMPP panel.
const DB_NAME = 'quiz_platform';
const DB_USER = 'root';
const DB_PASS = '';            // put your MySQL root password here if you set one
const DEBUG   = true;          // set to false when you deploy online

// ---- Base URL (auto-detected, e.g. "/quiz-platform"). Set manually if needed. ----
$__doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$__root = str_replace('\\', '/', __DIR__);
define('BASE_URL', rtrim(substr($__root, strlen($__doc)), '/'));

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $ex) {
    http_response_code(500);
    exit('Database connection failed. Check DB settings in config.php and import database.sql.'
        . (DEBUG ? '<br><br><b>Reason:</b> ' . htmlspecialchars($ex->getMessage()) : ''));
}

// ---- Helpers ----
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Invalid or expired request token. Go back, refresh the page and try again.');
    }
}

function flash(string $msg, string $type = 'success'): void
{
    $_SESSION['flash'] = [$msg, $type];
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!user()) {
        flash('Please log in to continue.', 'error');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (user()['role'] !== 'admin') {
        http_response_code(403);
        exit('Admins only.');
    }
}

function fmt_duration(int $secs): string
{
    $secs = max(0, $secs);
    $h = intdiv($secs, 3600);
    $m = intdiv($secs % 3600, 60);
    $s = $secs % 60;
    return $h > 0 ? sprintf('%dh %02dm %02ds', $h, $m, $s) : sprintf('%dm %02ds', $m, $s);
}

function csv_safe($v): string
{
    $v = (string) $v;
    // Prevent spreadsheet formula injection
    return ($v !== '' && strpos('=+-@', $v[0]) !== false) ? "'" . $v : $v;
}