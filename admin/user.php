<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_schema.php';
require_once __DIR__ . '/../includes/solar_monitoring_schema.php';
require_once __DIR__ . '/../includes/solar_simulation.php';
greenai_ensure_support_tables($pdo);
greenai_ensure_solar_monitoring_tables($pdo);

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php?as=admin");
    exit();
}

$adminName = $_SESSION['admin']['fullname'] ?? 'Support Agent';
$activeNav = 'users';
$userId = (int)($_GET['id'] ?? 0);

if ($userId <= 0) {
    header("Location: users.php");
    exit();
}

$stmt = $pdo->prepare("SELECT id, fullname, email, Residence, last_login, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$viewedUser = $stmt->fetch();

if (!$viewedUser) {
    header("Location: users.php");
    exit();
}

$initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $viewedUser['fullname']), 0, 2)) ?: 'U';

// Every resident has their own auto-provisioned home system (see
// includes/solar_monitoring_schema.php) â€” this page reads real per-resident
// figures from it instead of one fixed mock reading shared by everyone.
greenai_ensure_resident_system($pdo, $userId, $viewedUser['fullname'], $viewedUser['Residence'] ?? null);
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
    <title><?php echo htmlspecialchars($viewedUser['fullname']); ?> - Support Console | Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased m-0 p-0">

    <header class="w-full bg-[#15803d] px-4 sm:px-8 py-4 flex justify-between items-center shadow-xs">
        <div class="flex items-center gap-2.5 text-white">
            <div class="w-8 h-8 bg-white/15 rounded-xl flex items-center justify-center text-sm"><i class="fa-solid fa-headset"></i></div>
            <div>
                <h1 class="text-sm font-black leading-none">Green-AI Support Console</h1>
                <span class="text-[10px] font-bold text-emerald-100 tracking-wide">After-Sales Admin Panel</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs font-black text-white"><i class="fa-regular fa-user mr-1"></i> <?php echo htmlspecialchars($adminName); ?></span>
            <a href="logout.php" class="text-white/80 hover:text-rose-200 transition-colors text-xs" title="Sign Out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </header>

    <main class="p-4 sm:p-8 max-w-6xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <a href="users.php" class="inline-flex items-center gap-1.5 text-[11px] font-bold text-slate-400 hover:text-slate-600"><i class="fa-solid fa-arrow-left text-[10px]"></i> Back to all users</a>
            <div class="flex items-center flex-wrap gap-1 bg-white p-1 rounded-xl border border-slate-100 shadow-2xs text-[11px] font-bold text-gray-500 w-fit">
                <a href="dashboard.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 hover:text-slate-700"><i class="fa-solid fa-ticket"></i> Support Tickets</a>
                <a href="users.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 bg-[#15803d] text-white"><i class="fa-solid fa-users"></i> Users</a>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm font-black shrink-0"><?php echo htmlspecialchars($initials); ?></div>
                <div>
                    <span class="block text-sm font-black text-slate-900"><?php echo htmlspecialchars($viewedUser['fullname']); ?></span>
                    <span class="block text-[11px] text-slate-400 font-semibold"><?php echo htmlspecialchars($viewedUser['email']); ?> &middot; <?php echo htmlspecialchars($viewedUser['Residence'] ?? 'Residence not set'); ?></span>
                    <span class="inline-flex items-center gap-1.5 mt-1 text-[10px] font-bold text-slate-500">
                        <i class="fa-solid fa-solar-panel text-slate-300"></i> <?php echo number_format((float)$plant['capacity_kw'], 2); ?> kW &middot;
                        <span class="px-1.5 py-0.5 bg-slate-100 text-slate-600 rounded-md"><?php echo $systemTypeLabels[$plant['system_type']] ?? $plant['system_type']; ?></span>
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-4 text-[11px] font-bold text-slate-500">
                <div>
                    <span class="block text-[9px] uppercase text-slate-400 tracking-wide">Registered</span>
                    <span class="block text-slate-800"><?php echo htmlspecialchars(date('M j, Y', strtotime($viewedUser['created_at']))); ?></span>
                </div>
                <div>
                    <span class="block text-[9px] uppercase text-slate-400 tracking-wide">Last Login</span>
                    <span class="block text-slate-800"><?php echo !empty($viewedUser['last_login']) ? htmlspecialchars(date('M j, g:i A', strtotime($viewedUser['last_login']))) : 'Never'; ?></span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-1 bg-gray-50 p-1 rounded-xl border border-gray-100 text-[11px] font-bold text-gray-500 w-fit">
            <button type="button" onclick="switchPanel(this, 'monitoring')" class="admin-tab-btn bg-white text-slate-800 shadow-2xs px-3.5 py-1.5 rounded-lg transition-all cursor-pointer">Live Monitoring</button>
            <button type="button" onclick="switchPanel(this, 'history')" class="admin-tab-btn px-3.5 py-1.5 rounded-lg transition-all cursor-pointer">Energy History</button>
            <button type="button" onclick="switchPanel(this, 'cleanliness')" class="admin-tab-btn px-3.5 py-1.5 rounded-lg transition-all cursor-pointer">Panel Cleanliness</button>
        </div>

        <section id="panel-monitoring" class="space-y-6">
            <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-2xs flex items-center justify-between">
                <div class="flex items-center gap-2 text-xs font-black text-emerald-600">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> LIVE &mdash; Simulated Telemetry Feed for <?php echo htmlspecialchars($viewedUser['fullname']); ?>
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
        </section>

        <section id="panel-history" class="space-y-6 hidden">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">7-Day Solar Total</p>
                    <h3 class="text-xl font-black text-slate-900 mt-1"><?php echo number_format($weekSolarTotal, 1); ?> <span class="text-xs font-bold text-slate-400">kWh</span></h3>
                    <span class="text-[10px] <?php echo $solarWowPct >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?> font-bold block mt-1"><i class="fa-solid fa-arrow-<?php echo $solarWowPct >= 0 ? 'up' : 'down'; ?>"></i> <?php echo ($solarWowPct >= 0 ? '+' : '') . $solarWowPct; ?>% vs previous week</span>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">7-Day Consumption</p>
                    <h3 class="text-xl font-black text-slate-900 mt-1"><?php echo number_format($weekUsedTotal, 1); ?> <span class="text-xs font-bold text-slate-400">kWh</span></h3>
                    <span class="text-[10px] <?php echo $usedWowPct <= 0 ? 'text-emerald-600' : 'text-rose-600'; ?> font-bold block mt-1"><i class="fa-solid fa-arrow-<?php echo $usedWowPct >= 0 ? 'up' : 'down'; ?>"></i> <?php echo ($usedWowPct >= 0 ? '+' : '') . $usedWowPct; ?>% vs previous week</span>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Peak Generation Day</p>
                    <h3 class="text-xl font-black text-[#15803d] mt-1"><?php echo htmlspecialchars($peakDayLabel); ?></h3>
                    <span class="text-[10px] text-slate-400 font-bold block mt-1"><?php echo number_format($peakDay['solar'], 1); ?> kWh generated</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs overflow-hidden">
                <div class="p-5 border-b border-slate-50">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-table-list text-[#15803d]"></i> Daily Breakdown
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Date</th>
                                <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Solar Generated</th>
                                <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Consumed</th>
                                <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider"><?php echo $hasBattery ? 'Battery Stored' : 'Grid Export'; ?></th>
                                <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Net Savings</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                            <?php foreach ($days as $d): $label = $d['offset'] === 0 ? 'Today' : $now->modify("-{$d['offset']} days")->format('D, M j'); ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 text-slate-500"><?php echo $label; ?></td>
                                <td class="p-4 font-bold text-slate-900"><?php echo number_format($d['solar'], 1); ?> kWh</td>
                                <td class="p-4 font-bold text-slate-900"><?php echo number_format($d['used'], 1); ?> kWh</td>
                                <td class="p-4 font-bold text-slate-900"><?php echo number_format($d['third'], 1); ?> kWh</td>
                                <td class="p-4"><span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-lg border border-emerald-100">â‚±<?php echo $d['save']; ?> saved</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="panel-cleanliness" class="space-y-6 hidden">
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs space-y-4">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-broom text-[#15803d]"></i> Panel Cleanliness Monitor
                        </h3>
                        <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Simulated dust/debris reading for <?php echo htmlspecialchars($viewedUser['fullname']); ?>'s Rooftop Solar Array.</p>
                    </div>
                    <span class="px-2.5 py-1 <?php echo $cleanTier['badge']; ?> text-[10px] font-bold rounded-lg border shrink-0"><?php echo $cleanTier['label']; ?></span>
                </div>

                <div class="space-y-1.5">
                    <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div class="<?php echo $cleanTier['bar']; ?> h-2 rounded-full" style="width: <?php echo $cleanliness; ?>%;"></div>
                    </div>
                    <div class="flex justify-between text-[10px] font-bold text-gray-400">
                        <span>Panel Cleanliness</span>
                        <span class="text-slate-700 font-extrabold"><?php echo $cleanliness; ?>%</span>
                    </div>
                </div>

                <div class="bg-slate-50 border border-slate-100 p-3.5 rounded-xl">
                    <p class="text-[11px] font-semibold text-slate-600"><?php echo $cleanTier['note']; ?></p>
                </div>
            </div>
        </section>
    </main>

    <script>
    function switchPanel(buttonEl, panelName) {
        document.querySelectorAll('.admin-tab-btn').forEach(btn => {
            btn.className = 'admin-tab-btn px-3.5 py-1.5 rounded-lg transition-all cursor-pointer';
        });
        buttonEl.className = 'admin-tab-btn bg-white text-slate-800 shadow-2xs px-3.5 py-1.5 rounded-lg transition-all cursor-pointer';

        ['monitoring', 'history', 'cleanliness'].forEach(name => {
            document.getElementById('panel-' + name).classList.toggle('hidden', name !== panelName);
        });
    }
    </script>

</body>
</html>
