<?php
// 1. SYSTEM SESSION INITIALIZATION SAFETY HATCH
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. ERROR REPORTING WORKSPACE PIPELINE
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 3. STEP DOWN OVER ONE DIRECTORY LEVEL TO PULL GLOBAL DEPENDENCIES
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

// 4. SECURITY ROUTING GUARD
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

// Safely pull verified profile records from the application state
$fullname = $_SESSION['user']['fullname'] ?? 'User';
$email = $_SESSION['user']['email'] ?? 'user@example.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased flex m-0 p-0">

    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow min-w-0 flex flex-col min-h-screen">
        
        <header class="w-full bg-white border-b border-gray-100 px-8 py-5 flex justify-between items-center shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-user-gear text-[#15803d]"></i> Account Profile
                </h1>
                <p class="text-gray-400 text-xs font-semibold mt-0.5">Manage your identity credentials, session states, and verification tokens.</p>
            </div>
        </header>

        <main class="p-8 space-y-6 flex-grow overflow-y-auto max-w-4xl w-full">
            
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs flex flex-col sm:flex-row items-center gap-5">
                <div class="w-20 h-20 bg-[#15803d] text-white rounded-2xl flex items-center justify-center text-3xl font-black uppercase shadow-md shrink-0 border border-emerald-600/20">
                    <?php echo strtoupper(substr($fullname, 0, 2)); ?>
                </div>
                <div class="text-center sm:text-left leading-tight">
                    <h2 class="text-lg font-black text-slate-900"><?php echo htmlspecialchars($fullname); ?></h2>
                    <p class="text-xs font-semibold text-slate-400 mt-1"><?php echo htmlspecialchars($email); ?></p>
                    <div class="mt-3 flex flex-wrap gap-2 justify-center sm:justify-start">
                        <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-lg border border-emerald-100 flex items-center gap-1">
                            <i class="fa-solid fa-circle-check text-xs"></i> Google Account Verified
                        </span>
                        <span class="px-2.5 py-1 bg-slate-50 text-slate-600 text-[10px] font-bold rounded-lg border border-slate-200">
                            Role: Householder
                        </span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs overflow-hidden">
                <div class="p-5 border-b border-slate-50 bg-linear-to-r from-white to-slate-50/50">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-id-card text-[#15803d]"></i> Identity Profiles Data
                    </h3>
                </div>
                
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2">Account Full Name</label>
                        <div class="w-full bg-slate-50/80 border border-slate-200 text-xs font-semibold text-slate-800 rounded-xl px-4 py-3 flex items-center gap-3">
                            <i class="fa-solid fa-user text-slate-400"></i>
                            <input type="text" class="bg-transparent border-0 outline-hidden w-full text-slate-700 cursor-not-allowed" value="<?php echo htmlspecialchars($fullname); ?>" readonly />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2">Primary Email Registry</label>
                        <div class="w-full bg-slate-50/80 border border-slate-200 text-xs font-semibold text-slate-700 rounded-xl px-4 py-3 flex items-center gap-3">
                            <i class="fa-solid fa-envelope text-slate-400"></i>
                            <input type="email" class="bg-transparent border-0 outline-hidden w-full text-slate-500 cursor-not-allowed" value="<?php echo htmlspecialchars($email); ?>" readonly />
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

</body>
</html>