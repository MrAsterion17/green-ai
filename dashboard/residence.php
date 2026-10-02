<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if user is not logged in
if (!isset($_SESSION['user']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Your DB connection using 'greenai_db'
// Railway env vars when set, XAMPP defaults otherwise (see includes/db_env.php)
require_once __DIR__ . '/../includes/db_env.php';
$host = GREENAI_DB_HOST;
$port = GREENAI_DB_PORT;
$db   = GREENAI_DB_NAME;
$user = GREENAI_DB_USER;
$pass = GREENAI_DB_PASS;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch user data matching your exact database columns
$user_id = $_SESSION['user_id'] ?? $_SESSION['user']['id'];
$stmt = $pdo->prepare("SELECT fullname, Residence, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userData = $stmt->fetch();
?>

<h2>Household Details</h2>
<p><strong>Owner:</strong> <?php echo htmlspecialchars($userData['fullname'] ?? ''); ?></p>
<p><strong>Residence Address:</strong> <?php echo htmlspecialchars($userData['Residence'] ?? ''); ?></p>
<p><strong>Email:</strong> <?php echo htmlspecialchars($userData['email'] ?? ''); ?></p>