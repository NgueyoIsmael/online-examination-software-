<?php
// includes/db_connect.php  (REPLACES the previous version)
// One place that opens the database connection.
//  - On your computer (XAMPP) nothing is set, so it uses root / no password, like before.
//  - On Vercel you can set ONE variable, DATABASE_URL (the "Service URI" your database
//    provider shows), or the separate DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_SSL.
//    Separate DB_... variables, when present, win over DATABASE_URL.

if (!function_exists('app_env')) {
    function app_env($key, $default = null) {
        $v = getenv($key);
        if ($v === false || $v === '') {
            $v = $_ENV[$key] ?? ($_SERVER[$key] ?? null);
        }
        return ($v === null || $v === false || $v === '') ? $default : $v;
    }
}

if (!function_exists('app_db_connect')) {
    // $die = true  -> stop the page with a message if the connection fails
    // $die = false -> throw an exception instead (used by the health check)
    function app_db_connect($die = true) {
        // values from DATABASE_URL, e.g. mysql://user:password@host:3306/dbname?ssl-mode=REQUIRED
        $parts = [];
        $query = [];
        $url = app_env('DATABASE_URL');
        if ($url) {
            $parts = parse_url($url) ?: [];
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
            }
        }
        $url_db = isset($parts['path']) ? trim(rawurldecode($parts['path']), '/') : '';
        $url_ssl_mode = strtolower((string)($query['ssl-mode'] ?? ($query['sslmode'] ?? ($query['ssl_mode'] ?? ''))));
        $url_ssl = in_array($url_ssl_mode, ['required', 'require', 'verify_ca', 'verify_identity', 'verify-full', 'true', '1'], true) ? '1' : '0';

        $host = app_env('DB_HOST', $parts['host'] ?? 'localhost');
        $port = (int)app_env('DB_PORT', $parts['port'] ?? 3306);
        $name = app_env('DB_NAME', $url_db !== '' ? $url_db : 'online_exam');
        $user = app_env('DB_USER', isset($parts['user']) ? rawurldecode($parts['user']) : 'root');
        $pass = app_env('DB_PASS', isset($parts['pass']) ? rawurldecode($parts['pass']) : '');
        $ssl  = app_env('DB_SSL', $url_ssl) === '1';

        try {
            $conn = mysqli_init();
            $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
            $flags = $ssl ? (MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT) : 0;
            $conn->real_connect($host, $user, $pass, $name, $port, null, $flags);
            $conn->set_charset('utf8mb4');
            return $conn;
        } catch (Throwable $e) {
            if (!$die) {
                throw $e;
            }
            error_log('Database connection failed: ' . $e->getMessage());
            $debug = app_env('APP_DEBUG', '0') !== '0';
            die($debug ? 'Connection failed: ' . $e->getMessage() : 'Database connection failed. Please try again later.');
        }
    }
}
