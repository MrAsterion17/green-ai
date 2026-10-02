<?php
// 1. Ensure this configuration definition matches your Cloud Console credentials exactly
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '435672172043-your-actual-full-string-here.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR_CLIENT_SECRET_HERE');
define('GOOGLE_REDIRECT_URL', 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/login.php');

// --- FIXED: Removed the accidental closing tag that was here ---

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Database Configurations
// Railway env vars when set, XAMPP defaults otherwise (see db_env.php)
require_once __DIR__ . '/db_env.php';
$host = GREENAI_DB_HOST;
$port = GREENAI_DB_PORT;
$db   = GREENAI_DB_NAME;
$user = GREENAI_DB_USER;
$pass = GREENAI_DB_PASS;

try {
    // CRITICAL: Make sure this variable name is completely lowercase: $pdo
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
// Keep the tag open at the end of pure PHP files to prevent header layout errors