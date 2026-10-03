<?php
session_start();
$was_admin = (isset($_SESSION['role']) && $_SESSION['role'] == 'admin');
session_destroy();
header("Location: " . ($was_admin ? "/auth/admin_login.php" : "/auth/login.php"));
exit();
