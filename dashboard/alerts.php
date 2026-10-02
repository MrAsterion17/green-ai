<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

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
    <title>Alerts - Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased flex m-0 p-0">

    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow min-w-0 flex flex-col min-h-screen">
        <header class="w-full bg-white border-b border-gray-100 px-8 py-5 flex justify-between items-center shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-bell text-[#15803d]"></i> Notifications & Alerts
                </h1>
                <p class="text-gray-400 text-xs font-semibold mt-0.5">Critical grid threshold variance alert registers.</p>
            </div>
        </header>

        <main class="p-8 space-y-6 flex-grow overflow-y-auto max-w-6xl w-full">
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs">
                <p class="text-xs font-bold text-emerald-600"><i class="fa-solid fa-circle-check"></i> System state green. No anomalous grid consumption detected.</p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs">
                <div class="flex items-start gap-3">
                    <span class="w-2 h-2 rounded-full bg-amber-500 mt-1.5 shrink-0"></span>
                    <div class="flex-grow space-y-0.5">
                        <p class="text-xs font-bold text-slate-800 leading-tight">Dust accumulation detected on Rooftop Solar Array &mdash; generation efficiency reduced by ~6%. Schedule a cleaning.</p>
                        <span class="block text-[10px] text-slate-400 font-medium">35 mins ago</span>
                    </div>
                    <span class="px-2.5 py-1 bg-amber-50 text-amber-700 text-[10px] font-bold rounded-lg border border-amber-100 shrink-0 uppercase">Warning</span>
                </div>
            </div>
        </main>
    </div>

</body>
</html>