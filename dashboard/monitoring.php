<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/solar_monitoring_schema.php';
require_once __DIR__ . '/../includes/solar_simulation.php';
greenai_ensure_solar_monitoring_tables($pdo);

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$fullname = $_SESSION['user']['fullname'] ?? 'User';
$userId = (int)$_SESSION['user']['id'];

// Every resident has their own auto-provisioned home system — this page
// reads real figures from it instead of a fixed reading shared by everyone.
greenai_ensure_resident_system($pdo, $userId, $fullname, $_SESSION['user']['residence'] ?? null);
$stmt = $pdo->prepare("SELECT * FROM solar_plants WHERE user_id = ?");
$stmt->execute([$userId]);
$plant = $stmt->fetch();

$systemTypeLabels = ['grid_tied' => 'Grid-Tied', 'hybrid' => 'Hybrid', 'off_grid' => 'Off-Grid'];
extract(greenai_solar_dashboard_snapshot($plant));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Monitoring - Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased flex m-0 p-0">

    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow min-w-0 flex flex-col min-h-screen">

        <header class="w-full bg-white border-b border-gray-100 px-8 py-5 flex justify-between items-center shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-bolt text-[#15803d]"></i> Live Telemetry Stream
                </h1>
                <p class="text-gray-400 text-xs font-semibold mt-0.5">
                    <?php echo number_format((float)$plant['capacity_kw'], 2); ?> kW &middot; <?php echo $systemTypeLabels[$plant['system_type']] ?? $plant['system_type']; ?> rooftop system
                </p>
            </div>
        </header>

        <main class="p-8 space-y-6 flex-grow overflow-y-auto max-w-6xl w-full">

            <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-2xs flex items-center justify-between">
                <div class="flex items-center gap-2 text-xs font-black text-emerald-600">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> LIVE &mdash; Simulated Telemetry Feed
                </div>
                <span class="text-[10px] font-bold text-slate-400">Last synced <?php echo $now->format('h:i:s A'); ?></span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs space-y-3">
                    <div class="flex justify-between items-start">
                        <div class="space-y-0.5">
                            <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Solar Array</span>
                            <strong class="block text-2xl font-black text-slate-900 tracking-tight"><?php echo number_format($liveFlow['pv_kw'], 2); ?> <span class="text-xs font-black text-gray-400">kW</span></strong>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-amber-50 border border-amber-100 text-amber-500 flex items-center justify-center text-sm"><i class="fa-solid fa-solar-panel"></i></span>
                    </div>
                    <span class="text-[10px] font-bold <?php echo $liveFlow['pv_kw'] > 0 ? 'text-emerald-600' : 'text-slate-400'; ?>"><i class="fa-solid fa-arrow-trend-up"></i> <?php echo $liveFlow['pv_kw'] > 0 ? 'Generating' : 'No sunlight right now'; ?></span>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs space-y-3">
                    <div class="flex justify-between items-start">
                        <div class="space-y-0.5">
                            <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">House Load</span>
                            <strong class="block text-2xl font-black text-slate-900 tracking-tight"><?php echo number_format($liveFlow['load_kw'], 2); ?> <span class="text-xs font-black text-gray-400">kW</span></strong>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-blue-50 border border-blue-100 text-blue-500 flex items-center justify-center text-sm"><i class="fa-solid fa-house-laptop"></i></span>
                    </div>
                    <span class="text-[10px] font-bold text-slate-500">Stable consumption</span>
                </div>
                <?php if ($hasBattery): ?>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs space-y-3">
                    <div class="flex justify-between items-start">
                        <div class="space-y-0.5">
                            <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Battery Bank</span>
                            <strong class="block text-2xl font-black text-slate-900 tracking-tight"><?php echo $liveBattery['soc_pct']; ?> <span class="text-xs font-black text-gray-400">%</span></strong>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center text-sm"><i class="fa-solid fa-battery-three-quarters"></i></span>
                    </div>
                    <span class="text-[10px] font-bold text-emerald-600"><?php echo $liveBattery['charging'] ? 'Charging' : 'Discharging'; ?></span>
                </div>
                <?php else: ?>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs space-y-3">
                    <div class="flex justify-between items-start">
                        <div class="space-y-0.5">
                            <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Today's Production</span>
                            <strong class="block text-2xl font-black text-slate-900 tracking-tight"><?php echo number_format($todayProductionKwh, 1); ?> <span class="text-xs font-black text-gray-400">kWh</span></strong>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center text-sm"><i class="fa-solid fa-chart-line"></i></span>
                    </div>
                    <span class="text-[10px] font-bold text-slate-500">So far today, grid-tied (no battery)</span>
                </div>
                <?php endif; ?>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs space-y-3">
                    <div class="flex justify-between items-start">
                        <div class="space-y-0.5">
                            <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider"><?php echo $liveFlow['grid_kw'] > 0 ? 'Grid Import' : 'Grid Export'; ?></span>
                            <strong class="block text-2xl font-black text-slate-900 tracking-tight"><?php echo number_format(abs($liveFlow['grid_kw']), 2); ?> <span class="text-xs font-black text-gray-400">kW</span></strong>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-purple-50 border border-purple-100 text-purple-500 flex items-center justify-center text-sm"><i class="fa-solid fa-tower-broadcast"></i></span>
                    </div>
                    <span class="text-[10px] font-bold text-slate-500"><?php echo $liveFlow['grid_kw'] > 0 ? 'Drawing from the grid' : 'Surplus feeding grid'; ?></span>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs overflow-hidden">
                <div class="p-5 border-b border-slate-50">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-stream text-[#15803d]"></i> Recent Telemetry Ticks
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Time</th>
                                <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Solar</th>
                                <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Load</th>
                                <?php if ($hasBattery): ?><th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Battery</th><?php endif; ?>
                                <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                            <?php foreach ($ticks as $t): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 text-slate-500"><?php echo $t['time']->format('h:i:s A'); ?></td>
                                <td class="p-4 font-bold text-slate-900"><?php echo number_format($t['solar'], 2); ?> kW</td>
                                <td class="p-4 font-bold text-slate-900"><?php echo number_format($t['load'], 2); ?> kW</td>
                                <?php if ($hasBattery): ?><td class="p-4 font-bold text-slate-900"><?php echo $t['batt']; ?>%</td><?php endif; ?>
                                <td class="p-4"><span class="px-2.5 py-1 <?php echo $tickStatus['class']; ?> text-[10px] font-bold rounded-lg border"><?php echo $tickStatus['label']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

</body>
</html>
