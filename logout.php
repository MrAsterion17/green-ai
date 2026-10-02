<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all active tracking matrices
$_SESSION = array();

// Completely destroy the session cookie file storage completely
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Route back to the central login gate safely
header("Location: login.php");
exit();