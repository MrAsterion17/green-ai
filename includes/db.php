<?php

// Railway env vars when set, XAMPP defaults otherwise (see db_env.php)
require_once __DIR__ . '/db_env.php';
$host = GREENAI_DB_HOST;
$port = GREENAI_DB_PORT;
$user = GREENAI_DB_USER;
$password = GREENAI_DB_PASS;
$database = GREENAI_DB_NAME;

$conn = mysqli_connect($host, $user, $password, $database, $port);

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}
?>