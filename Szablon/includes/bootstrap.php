<?php
/**
 * Wspólny start każdej strony: konfiguracja, sesja, funkcje pomocnicze.
 */
define('APP_ROOT', dirname(__DIR__));

$GLOBALS['__cfg'] = require __DIR__ . '/config.php';
if (is_file(__DIR__ . '/config.local.php')) {
    $GLOBALS['__cfg'] = array_replace_recursive($GLOBALS['__cfg'], require __DIR__ . '/config.local.php');
}

/** Odczyt konfiguracji kropkowanym kluczem, np. cfg('db.host'). */
function cfg($key, $default = null)
{
    $value = $GLOBALS['__cfg'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

date_default_timezone_set(cfg('timezone', 'Europe/Warsaw'));
mb_internal_encoding('UTF-8');

if (cfg('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

/* ---- Względny prefiks adresów (zależnie od głębokości bieżącego skryptu) ---- */
(function () {
    $depth  = 0;
    $script = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
    $root   = realpath(APP_ROOT);
    if ($script && $root && strpos($script, $root) === 0) {
        $rel   = ltrim(str_replace('\\', '/', substr($script, strlen($root))), '/');
        $depth = substr_count($rel, '/');
    }
    define('BASE', str_repeat('../', $depth));
})();

function url($path = '')
{
    return BASE . ltrim($path, '/');
}

function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

/** Skrócona wersja htmlspecialchars. */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_post()
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Przycięta wartość tekstowa z $_POST. */
function post($key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/** Przycięta wartość tekstowa z $_GET. */
function get($key, $default = '')
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/** JSON bezpieczny do osadzenia w HTML (np. w <script type="application/json">). */
function json_attr($data)
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/* ---- Nagłówki bezpieczeństwa ---- */
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

/* ---- Sesja ---- */
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name('HP_SESSID');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $https,
    ]);
    session_start();

    // Wygaśnięcie sesji po bezczynności
    if (!empty($_SESSION['user'])) {
        $idle = (int)cfg('session_idle_timeout', 7200);
        if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $idle) {
            unset($_SESSION['user']);
            $_SESSION['timed_out'] = true;
        }
        $_SESSION['last_activity'] = time();
    }
}

/* ---- Komunikaty flash ---- */
function flash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flash()
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/* ---- CSRF ---- */
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check()
{
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('Nieprawidłowy token formularza. Odśwież stronę i spróbuj ponownie.');
    }
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/employees.php';
require_once __DIR__ . '/schedule.php';
require_once __DIR__ . '/layout.php';
