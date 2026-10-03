<?php
// api/index.php  (NEW) - the single entry point for Vercel.
// vercel.json sends EVERY request here. This file finds the right page of the website,
// prepares the session, and runs that page, so none of your pages had to change.

define('RUNNING_ON_VERCEL', true);

$__root = dirname(__DIR__);

function vc_env($key) {
    $v = getenv($key);
    return ($v === false || $v === '') ? null : $v;
}
function vc_fail($code, $text) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $text;
    exit();
}

// Set APP_DEBUG=1 in Vercel's Environment Variables while setting up, to see errors on the page.
$__debug = vc_env('APP_DEBUG') !== null && vc_env('APP_DEBUG') !== '0';
ini_set('display_errors', $__debug ? '1' : '0');
error_reporting(E_ALL);

// Vercel's proxy handles HTTPS, tell the pages about it (used for links and secure cookies)
if (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
// Optional settings from environment variables
if (vc_env('CERT_SECRET')) define('CERT_SECRET', vc_env('CERT_SECRET'));
if (vc_env('APP_TIMEZONE')) define('APP_TIMEZONE', vc_env('APP_TIMEZONE'));

// ---- which file was asked for? ----
$__path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$__path = rawurldecode(($__path === null || $__path === false) ? '/' : $__path);
if (strpos($__path, "\0") !== false || strpos($__path, '..') !== false || strpos($__path, '\\') !== false) {
    vc_fail(400, 'Bad request');
}
if ($__path === '' || $__path === '/') {
    $__path = '/index.php';
} elseif (substr($__path, -1) === '/') {
    $__path .= 'index.php';
}

// ---- health check (only when APP_DEBUG is on) ----
if ($__path === '/__health') {
    if (!$__debug) vc_fail(404, 'Not Found');
    header('Content-Type: text/plain; charset=UTF-8');
    echo "PHP version: " . PHP_VERSION . "\n";
    echo "mysqli: " . (extension_loaded('mysqli') ? 'yes' : 'NO') . "\n";
    echo "openssl: " . (extension_loaded('openssl') ? 'yes' : 'NO') . "\n";
    foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_SSL', 'CERT_SECRET'] as $k) {
        echo "env $k: " . (vc_env($k) !== null ? 'set' : 'not set') . "\n";
    }
    foreach (['public/css/style.css', 'public/img/logo.svg', 'includes/header.php', 'config/db.php'] as $f) {
        echo "file $f: " . (is_file($__root . '/' . $f) ? 'found' : 'MISSING') . "\n";
    }
    require_once $__root . '/includes/db_connect.php';
    try {
        $c = app_db_connect(false);
        echo "database: connected\n";
        $r = $c->query("SHOW TABLES LIKE 'php_sessions'");
        echo "table php_sessions: " . ($r && $r->num_rows ? 'found' : 'MISSING (run sql/vercel_sessions.sql)') . "\n";
        $r = $c->query("SHOW TABLES LIKE 'exams'");
        echo "table exams: " . ($r && $r->num_rows ? 'found' : 'MISSING (import sql/full_database.sql)') . "\n";
    } catch (Throwable $e) {
        echo "database: FAILED - " . $e->getMessage() . "\n";
    }
    exit();
}

$__parts = explode('/', ltrim($__path, '/'));
$__first = $__parts[0];
$__name  = end($__parts);
$__ext   = strtolower(pathinfo($__path, PATHINFO_EXTENSION));

// ---- pictures, styles, scripts: send them straight back ----
$__static = [
    'css' => 'text/css; charset=UTF-8', 'js' => 'application/javascript; charset=UTF-8',
    'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
    'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
];
if (isset($__static[$__ext])) {
    $__ok = ($__first === 'public' && count($__parts) >= 2) || $__path === '/favicon.ico';
    $__file = $__root . $__path;
    if (!$__ok || !is_file($__file)) vc_fail(404, 'Not Found');
    header('Content-Type: ' . $__static[$__ext]);
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . filesize($__file));
    readfile($__file);
    exit();
}

// ---- PHP pages ----
// Allowed: the PHP files in the project root and in admin/, auth/, student/, verification/.
// Never reachable from the browser: includes/, config/, api/, files starting with "_".
if ($__ext !== 'php') vc_fail(404, 'Not Found');
$__ok = (count($__parts) === 1 && $__name !== 'test_db.php')
     || (count($__parts) >= 2 && in_array($__first, ['admin', 'auth', 'student', 'verification'], true));
if ($__name === '' || $__name[0] === '_') $__ok = false;

$__file = $__root . $__path;
if (!$__ok || !is_file($__file)) vc_fail(404, 'Not Found');
$__real = realpath($__file);
if ($__real === false || strpos($__real, realpath($__root)) !== 0) vc_fail(404, 'Not Found');

// ---- sessions are kept in the database (Vercel has no permanent disk) ----
require_once $__root . '/includes/db_session.php';
ini_set('session.gc_maxlifetime', '21600');   // 6 hours, so long exams do not log students out
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_set_save_handler(new DbSessionHandler(), true);

// make it look to the page as if it was opened directly
$_SERVER['SCRIPT_NAME']     = $__path;
$_SERVER['PHP_SELF']        = $__path;
$_SERVER['SCRIPT_FILENAME'] = $__file;
$_SERVER['DOCUMENT_ROOT']   = $__root;
chdir(dirname($__file));

require $__file;
