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
    <title>Insights & Tips - Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased flex m-0 p-0">

    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow min-w-0 flex flex-col min-h-screen">
        <header class="w-full bg-white border-b border-gray-100 px-8 py-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles text-[#15803d]"></i> Insights & Tips
                </h1>
                <p class="text-gray-400 text-xs font-semibold mt-0.5">Automated eco-efficiency recommendations matrix summaries.</p>
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?php
                $tips = [
                    ['icon' => '☀️', 'title' => 'Shift heavy loads to midday', 'body' => 'Run your washing machine and dryer between 10 AM–2 PM when solar generation peaks.', 'save' => 'Save up to ₱180/month'],
                    ['icon' => '🔋', 'title' => 'Let the battery cover evening peaks', 'body' => 'Your battery is consistently reaching 80%+ by sunset &mdash; draw from it instead of the grid after 6 PM.', 'save' => 'Save up to ₱95/month'],
                    ['icon' => '🌡️', 'title' => 'Raise AC setpoint by 1–2°C', 'body' => 'Small temperature adjustments during peak sun hours reduce cooling load without much comfort loss.', 'save' => 'Save up to ₱120/month'],
                    ['icon' => '💡', 'title' => 'Switch remaining bulbs to LED', 'body' => 'Two rooms in your monitored circuits are still drawing incandescent-level loads at night.', 'save' => 'Save up to ₱60/month'],
                    ['icon' => '🔌', 'title' => 'Unplug idle standby devices', 'body' => 'Detected consistent phantom load overnight from entertainment center outlets.', 'save' => 'Save up to ₱45/month'],
                    ['icon' => '🌧️', 'title' => 'Pre-charge battery before cloudy days', 'body' => 'Weather sync shows lower solar output this Friday &mdash; top up battery storage a day ahead.', 'save' => 'Avoid grid draw spikes'],
                ];
                foreach ($tips as $tip):
                ?>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs flex gap-3.5 items-start">
                    <span class="text-2xl shrink-0"><?php echo $tip['icon']; ?></span>
                    <div class="space-y-1">
                        <h3 class="text-xs font-black text-slate-900"><?php echo htmlspecialchars($tip['title']); ?></h3>
                        <p class="text-[11px] text-slate-500 font-semibold leading-relaxed"><?php echo $tip['body']; ?></p>
                        <span class="inline-block mt-1 px-2.5 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-lg border border-emerald-100"><?php echo htmlspecialchars($tip['save']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

</body>
</html>