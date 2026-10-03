<?php
// config/db.php  (REPLACES the old file)
// Same $conn as before, but the login details can now come from environment variables.
// With nothing set (XAMPP) it connects to localhost / online_exam / root / no password.
require_once __DIR__ . '/../includes/db_connect.php';

$conn = app_db_connect();
