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

greenai_ensure_resident_system($pdo, $userId, $fullname, $_SESSION['user']['residence'] ?? null);
$stmt = $pdo->prepare("SELECT * FROM solar_plants WHERE user_id = ?");
$stmt->execute([$userId]);
$plant = $stmt->fetch();

extract(greenai_solar_dashboard_snapshot($plant));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Energy History - Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased flex m-0 p-0">

    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow min-w-0 flex flex-col min-h-screen">
        <header class="w-full bg-white border-b border-gray-100 px-8 py-5 flex justify-between items-center shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-[#15803d]"></i> Energy History
                </h1>
                <p class="text-gray-400 text-xs font-semibold mt-0.5">Last 7 days of generation and consumption for your home system.</p>
            </div>
        </header>

        <main class="p-8 space-y-6 flex-grow overflow-y-auto max-w-6xl w-full">

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
                                <td class="p-4"><span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-lg border border-emerald-100">₱<?php echo $d['save']; ?> saved</span></td>
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
