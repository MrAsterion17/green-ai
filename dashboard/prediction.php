<?php
// 1. FORCED DIAGNOSTICS FOR CODES
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. LIFELINE SESSION TRACKER INITIALIZATION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. FAIL-SAFE ABSOLUTE PATH MAPPING
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

// 4. SECURITY ROUTING GUARD
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$fullname = $_SESSION['user']['fullname'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Predictions - Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased flex m-0 p-0">

    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow min-w-0 flex flex-col min-h-screen">
        
        <header class="w-full bg-white border-b border-gray-100 px-8 py-5 flex justify-between items-center shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-brain text-[#15803d]"></i> AI Energy Predictions
                </h1>
                <p class="text-gray-400 text-xs font-semibold mt-0.5">Predictive modeling analysis metrics.</p>
            </div>
        </header>

        <main class="p-8 space-y-6 flex-grow overflow-y-auto max-w-6xl w-full">
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs">
                <p class="text-xs font-bold text-slate-500">Your AI prediction modules are running and properly authenticated.</p>
            </div>
        </main>
    </div>

</body>
</html>