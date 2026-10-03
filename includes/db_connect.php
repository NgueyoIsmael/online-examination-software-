<?php
// includes/db_connect.php  (NEW)
// One place that opens the database connection.
//  - On your computer (XAMPP) nothing is set, so it uses root / no password, like before.
//  - On Vercel you set DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS (and DB_SSL=1 if your
//    database provider requires a secure connection) in Vercel's Environment Variables.

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
        $host = app_env('DB_HOST', 'localhost');
        $port = (int)app_env('DB_PORT', 3306);
        $name = app_env('DB_NAME', 'online_exam');
        $user = app_env('DB_USER', 'root');
        $pass = app_env('DB_PASS', '');
        $ssl  = app_env('DB_SSL', '0') === '1';

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
