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
require_once __DIR__ . '/../includes/solar_monitoring_schema.php';
require_once __DIR__ . '/../includes/solar_simulation.php';
greenai_ensure_solar_monitoring_tables($pdo);

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$fullname = $_SESSION['user']['fullname'] ?? 'User';
$userId = (int)$_SESSION['user']['id'];

// Every resident has their own auto-provisioned home system â€” this page
// reads real per-resident figures from it instead of one fixed mock reading
// (previously a hardcoded utility-scale plant) shared by everyone.
greenai_ensure_resident_system($pdo, $userId, $fullname, $_SESSION['user']['residence'] ?? null);
$stmt = $pdo->prepare("SELECT * FROM solar_plants WHERE user_id = ?");
$stmt->execute([$userId]);
$plant = $stmt->fetch();

$systemTypeLabels = ['grid_tied' => 'Grid-Tied', 'hybrid' => 'Hybrid', 'off_grid' => 'Off-Grid'];
extract(greenai_solar_dashboard_snapshot($plant));

$plantName = $plant['name'];
$dataLoggerSN = 'GA-' . str_pad((string)$plant['id'], 6, '0', STR_PAD_LEFT);
$equipment = greenai_solar_device_profile($plant);

$devices = [
    ['name' => 'Solar Panels', 'type' => 'panels', 'status' => 'Normal', 'sn' => null, 'metrics' => [
        'Array' => $equipment['panel_count'] . ' × ' . $equipment['panel_wattage'] . 'W', 'Total DC Capacity' => number_format((float)$plant['capacity_kw'], 2) . ' kWp',
    ]],
    ['name' => $equipment['inverter_model'], 'type' => 'inverter', 'status' => $liveFlow['pv_kw'] > 0 ? 'Normal' : 'Standby', 'sn' => 'INV-' . strtoupper(substr(md5((string)$plant['id']), 0, 8)), 'metrics' => [
        'Daily generation' => number_format($todayProductionKwh, 2) . ' kWh', 'Total active power' => number_format($liveFlow['pv_kw'], 2) . ' kW',
    ]],
    ['name' => 'Smart Meter', 'type' => 'meter', 'status' => 'Normal', 'sn' => null, 'metrics' => [
        'Meter active power' => number_format(abs($liveFlow['grid_kw']), 2) . ' kW', 'Direction' => $liveFlow['grid_kw'] > 0 ? 'Importing' : 'Exporting',
    ]],
    ['name' => $equipment['comm_label'], 'type' => 'comm', 'status' => 'Normal', 'sn' => $dataLoggerSN, 'metrics' => [
        $equipment['comm_metric_label'] => $equipment['comm_metric_value'],
    ]],
];
if ($hasBattery) {
    $devices[] = ['name' => 'Battery Inverter', 'type' => 'battery', 'status' => 'Normal', 'sn' => 'BAT-' . strtoupper(substr(md5('battery' . $plant['id']), 0, 8)), 'metrics' => [
        'State of charge' => $liveBattery['soc_pct'] . '%', 'Mode' => $liveBattery['charging'] ? 'Charging' : 'Discharging',
    ]];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devices - Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased flex m-0 p-0">

    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow min-w-0 flex flex-col min-h-screen">
        <header class="w-full bg-white border-b border-gray-100 px-8 py-5 flex justify-between items-center shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-microchip text-[#15803d]"></i> Connected IoT Devices
                </h1>
                <p class="text-gray-400 text-xs font-semibold mt-0.5">Hardware terminal link diagnostics registry dashboard.</p>
            </div>
        </header>

        <main class="p-8 space-y-6 flex-grow overflow-y-auto max-w-6xl w-full">
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs">
                <p class="text-xs font-bold text-slate-500">Smart appliance relay arrays are listening online.</p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs space-y-4">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-server text-[#15803d]"></i> Plant Device Registry
                        </h3>
                        <p class="text-[10px] font-semibold text-slate-400 mt-0.5"><?php echo htmlspecialchars($plantName); ?> &middot; Data logger S/N <?php echo htmlspecialchars($dataLoggerSN); ?></p>
                    </div>
                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 text-[10px] font-bold rounded-lg shrink-0">Total <?php echo count($devices); ?></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($devices as $device): ?>
                        <?php $isOnline = $device['status'] === 'Normal'; ?>
                        <div class="border border-slate-100 rounded-xl p-4 space-y-3 bg-slate-50/40">
                            <div class="flex justify-between items-start">
                                <span class="text-xs font-black text-slate-900"><?php echo htmlspecialchars($device['name']); ?></span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[9px] font-bold border <?php echo $isOnline ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-gray-100 text-gray-500 border-gray-200'; ?>">
                                    <i class="fa-solid <?php echo $isOnline ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i> <?php echo htmlspecialchars($device['status']); ?>
                                </span>
                            </div>
                            <?php if ($device['sn']): ?>
                                <p class="text-[10px] font-semibold text-slate-400">S/N: <?php echo htmlspecialchars($device['sn']); ?></p>
                            <?php endif; ?>
                            <div class="grid grid-cols-2 gap-2 text-[10px]">
                                <?php foreach ($device['metrics'] as $label => $value): ?>
                                    <div class="bg-white border border-slate-100 rounded-lg p-2">
                                        <span class="block text-gray-400 font-semibold"><?php echo htmlspecialchars($label); ?></span>
                                        <span class="block text-slate-800 font-black"><?php echo htmlspecialchars($value); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs space-y-4">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-broom text-[#15803d]"></i> Panel Cleanliness Monitor
                        </h3>
                        <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Simulated dust/debris reading for the Rooftop Solar Array.</p>
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

                <div class="flex items-center justify-between gap-3 bg-slate-50 border border-slate-100 p-3.5 rounded-xl">
                    <p class="text-[11px] font-semibold text-slate-600"><?php echo $cleanTier['note']; ?></p>
                    <?php if ($cleanliness < 85): ?>
                    <a href="support.php?category=equipment&subject=<?php echo urlencode('Solar panel needs cleaning'); ?>" class="shrink-0 inline-flex items-center gap-1.5 bg-[#1b5e20] hover:bg-[#144517] text-white text-[11px] font-bold px-3.5 py-2 rounded-xl transition-all">
                        <i class="fa-solid fa-headset"></i> Report to Support
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

</body>
</html>