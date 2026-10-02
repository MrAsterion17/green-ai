<?php
// 1. ENABLE GLOBAL ROUTING DIAGNOSTICS & ACTIVE SESSION VERIFICATION
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Locate security verification structures by stepping up out of the dashboard folder
require_once '../includes/session.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$fullname = $_SESSION['user']['fullname'] ?? 'Jc Salvador';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Energy Analytics Reports - Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased flex m-0 p-0">

    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow min-w-0 flex flex-col min-h-screen">
        
        <header class="w-full bg-white border-b border-gray-100 px-8 py-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice-dollar text-[#15803d]"></i> Energy Logs & Compliance Reports
                </h1>
                <p class="text-gray-400 text-xs font-semibold mt-0.5">Export historical microgrid logging tables, summary breakdowns, and auditing data structures.</p>
            </div>
            
            <button onclick="window.print()" class="px-4 py-2 bg-[#15803d] hover:bg-[#12652f] text-white text-xs font-black rounded-xl transition-all shadow-xs flex items-center gap-2 cursor-pointer">
                <i class="fa-solid fa-download"></i> Print Full Audit
            </button>
        </header>

        <main class="p-8 space-y-6 flex-grow overflow-y-auto max-w-6xl w-full">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Total Generation Billed</p>
                    <h3 class="text-xl font-black text-slate-900 mt-1">286.4 <span class="text-xs font-bold text-slate-400">kWh</span></h3>
                    <span class="text-[10px] text-emerald-600 font-bold block mt-1"><i class="fa-solid fa-arrow-up"></i> +15% from last period</span>
                </div>
                
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Total Consumption Ledger</p>
                    <h3 class="text-xl font-black text-slate-900 mt-1">225.7 <span class="text-xs font-bold text-slate-400">kWh</span></h3>
                    <span class="text-[10px] text-emerald-600 font-bold block mt-1"><i class="fa-solid fa-arrow-down"></i> -8% reduction vector</span>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Net Carbon Offsetting</p>
                    <h3 class="text-xl font-black text-[#15803d] mt-1">68.3 <span class="text-xs font-bold text-emerald-700/60">kg CO₂</span></h3>
                    <span class="text-[10px] text-slate-400 font-bold block mt-1">Equivalent to planting 1 tree</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs overflow-hidden">
                <div class="p-5 border-b border-slate-50 flex justify-between items-center bg-linear-to-r from-white to-slate-50/50">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-list text-[#15803d]"></i> Historical Microgrid Data Records
                        </h3>
                        <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Auditable data matrix tracking household consumption patterns.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Timestamp Log</th>
                                <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Source Category</th>
                                <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Metric Output</th>
                                <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Verification Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 text-slate-500">2026-07-07 10:15 AM</td>
                                <td class="p-4 flex items-center gap-2"><i class="fa-solid fa-sun text-amber-500 text-sm"></i> Solar Generation Array</td>
                                <td class="p-4 font-bold text-slate-900">2.45 kW</td>
                                <td class="p-4"><span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-lg border border-emerald-100">Verified</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 text-slate-500">2026-07-07 09:30 AM</td>
                                <td class="p-4 flex items-center gap-2"><i class="fa-solid fa-house-chimney text-[#15803d] text-sm"></i> Main House Load</td>
                                <td class="p-4 font-bold text-slate-900">1.32 kW</td>
                                <td class="p-4"><span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-lg border border-emerald-100">Verified</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 text-slate-500">2026-07-06 04:45 PM</td>
                                <td class="p-4 flex items-center gap-2"><i class="fa-solid fa-battery-three-quarters text-emerald-600 text-sm"></i> Battery Bank Storage</td>
                                <td class="p-4 font-bold text-slate-900">4.78 kWh</td>
                                <td class="p-4"><span class="px-2.5 py-1 bg-amber-50 text-amber-700 text-[10px] font-bold rounded-lg border border-amber-100">Pending Sync</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

</body>
</html>