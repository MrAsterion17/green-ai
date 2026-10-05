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
require_once __DIR__ . '/../includes/weather.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$fullname = $_SESSION['user']['fullname'] ?? 'User';

$weather = greenai_get_weather();
$hasWeather = empty($weather['error']) && isset($weather['current_weather']);
$weatherTemp = $hasWeather ? round($weather['current_weather']['temperature']) : null;
$weatherInfo = $hasWeather ? greenai_weather_code_info($weather['current_weather']['weathercode']) : ['label' => 'Unavailable', 'icon' => 'fa-cloud-question'];
$weatherLabel = $weather['label'] ?? GREENAI_WEATHER_LABEL;
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
        <header class="w-full bg-white border-b border-gray-100 px-8 py-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-bell text-[#15803d]"></i> Notifications & Alerts
                </h1>
                <p class="text-gray-400 text-xs font-semibold mt-0.5">Critical grid threshold variance alert registers.</p>
            </div>
            <div class="flex items-center gap-3 text-xs font-bold text-slate-600">
                <div class="flex items-center gap-2 bg-gray-50 border border-gray-100 px-3 py-1.5 rounded-xl">
                    <span class="text-gray-400 text-[10px] uppercase font-black">System Status</span>
                    <span class="flex items-center gap-1 text-emerald-600 font-black"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Normal</span>
                </div>
                <div class="flex items-center gap-2 bg-gray-50 border border-gray-100 px-3 py-1.5 rounded-xl">
                    <i class="fa-solid <?php echo $weatherInfo['icon']; ?> text-amber-500 text-sm"></i>
                    <div>
                        <span class="block font-black text-slate-800 leading-none"><?php echo $hasWeather ? $weatherTemp : '--'; ?>°C</span>
                        <span class="text-[9px] text-gray-400 leading-none"><?php echo htmlspecialchars(explode(',', $weatherLabel)[0]); ?></span>
                    </div>
                </div>
                <div class="inline-flex items-center gap-2 rounded-2xl bg-emerald-50 border border-emerald-100 px-3 py-2 text-xs font-black text-emerald-700">
                    <i class="fa-solid fa-circle-user"></i> <?php echo htmlspecialchars($fullname); ?>
                </div>
            </div>
        </header>

        <main class="p-8 space-y-6 flex-grow overflow-y-auto max-w-6xl w-full">

            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs">
                <p class="text-xs font-bold text-emerald-600"><i class="fa-solid fa-circle-check"></i> System state green. No anomalous grid consumption detected.</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs overflow-hidden">
                <div class="p-5 border-b border-slate-50">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Recent Alert Log</h3>
                    <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Simulated events raised by the microgrid monitoring engine.</p>
                </div>
                <div class="divide-y divide-slate-50">
                    <?php
                    $alerts = [
                        ['level' => 'warning',  'icon' => 'fa-broom',                   'msg' => 'Dust accumulation detected on Rooftop Solar Array &mdash; generation efficiency reduced by ~6%. Schedule a cleaning.', 'time' => '35 mins ago'],
                        ['level' => 'info',     'icon' => 'fa-circle-info',            'msg' => 'System microgrid architectures functioning within normal constraints.', 'time' => '10 mins ago'],
                        ['level' => 'warning',  'icon' => 'fa-triangle-exclamation',    'msg' => 'Peak demand escalation vector noted yesterday at 8:00 PM.',              'time' => 'Yesterday, 8:00 PM'],
                        ['level' => 'critical', 'icon' => 'fa-bolt',                    'msg' => 'Backyard Security Cam went offline &mdash; check device connection.',    'time' => 'Yesterday, 3:12 PM'],
                        ['level' => 'warning',  'icon' => 'fa-battery-quarter',         'msg' => 'Battery storage dipped below 20% threshold during cloud cover.',         'time' => '2 days ago'],
                        ['level' => 'info',     'icon' => 'fa-cloud-sun',               'msg' => 'Weather sync updated forecast thresholds for solar generation.',          'time' => '2 days ago'],
                        ['level' => 'info',     'icon' => 'fa-plug',                    'msg' => 'Kitchen Smart Outlet reconnected after brief signal loss.',               'time' => '3 days ago'],
                    ];
                    $levelStyles = [
                        'info'     => ['dot' => 'bg-blue-500',   'badge' => 'bg-blue-50 text-blue-700 border-blue-100'],
                        'warning'  => ['dot' => 'bg-amber-500',  'badge' => 'bg-amber-50 text-amber-700 border-amber-100'],
                        'critical' => ['dot' => 'bg-rose-500',   'badge' => 'bg-rose-50 text-rose-700 border-rose-100'],
                    ];
                    foreach ($alerts as $a):
                        $style = $levelStyles[$a['level']];
                    ?>
                    <div class="flex items-start gap-3 p-4">
                        <span class="w-2 h-2 rounded-full <?php echo $style['dot']; ?> mt-1.5 shrink-0"></span>
                        <div class="flex-grow space-y-0.5">
                            <p class="text-xs font-bold text-slate-800 leading-tight"><?php echo $a['msg']; ?></p>
                            <span class="block text-[10px] text-slate-400 font-medium"><?php echo htmlspecialchars($a['time']); ?></span>
                        </div>
                        <span class="px-2.5 py-1 <?php echo $style['badge']; ?> text-[10px] font-bold rounded-lg border shrink-0 uppercase"><?php echo $a['level']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

</body>
</html>