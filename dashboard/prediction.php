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
require_once __DIR__ . '/../includes/weather.php';

// 4. SECURITY ROUTING GUARD
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
    <title>AI Predictions - Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased flex m-0 p-0">

    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow min-w-0 flex flex-col min-h-screen">
        
        <header class="w-full bg-white border-b border-gray-100 px-8 py-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-brain text-[#15803d]"></i> AI Energy Predictions
                </h1>
                <p class="text-gray-400 text-xs font-semibold mt-0.5">Predictive modeling analysis metrics.</p>
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

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs space-y-1">
                    <span class="block text-[10px] text-gray-400 font-black uppercase tracking-wide">Tomorrow's Forecast</span>
                    <div class="flex items-baseline gap-1">
                        <strong class="text-2xl font-black text-slate-900 tracking-tight">19.2</strong>
                        <span class="text-xs font-bold text-gray-400">kWh</span>
                    </div>
                    <span class="text-[10px] font-bold text-emerald-600 block"><i class="fa-solid fa-arrow-trend-up"></i> 12% higher than today</span>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs space-y-1">
                    <span class="block text-[10px] text-gray-400 font-black uppercase tracking-wide">7-Day Forecast</span>
                    <div class="flex items-baseline gap-1">
                        <strong class="text-2xl font-black text-slate-900 tracking-tight">124.6</strong>
                        <span class="text-xs font-bold text-gray-400">kWh</span>
                    </div>
                    <span class="text-[10px] font-bold text-emerald-600 block"><i class="fa-solid fa-arrow-trend-up"></i> 8% above average</span>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs space-y-1">
                    <span class="block text-[10px] text-gray-400 font-black uppercase tracking-wide">Model Confidence</span>
                    <div class="flex items-baseline gap-1">
                        <strong class="text-2xl font-black text-[#15803d] tracking-tight">92</strong>
                        <span class="text-xs font-bold text-gray-400">%</span>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400 block">Based on 30-day weather + usage patterns</span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs space-y-4">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">7-Day Generation Outlook</h3>
                    <div class="space-y-3">
                        <?php
                        $forecast = [
                            ['day' => 'Mon', 'kwh' => 18.4, 'pct' => 76],
                            ['day' => 'Tue', 'kwh' => 19.2, 'pct' => 80],
                            ['day' => 'Wed', 'kwh' => 21.0, 'pct' => 88],
                            ['day' => 'Thu', 'kwh' => 16.8, 'pct' => 70],
                            ['day' => 'Fri', 'kwh' => 15.5, 'pct' => 64],
                            ['day' => 'Sat', 'kwh' => 17.9, 'pct' => 74],
                            ['day' => 'Sun', 'kwh' => 15.8, 'pct' => 66],
                        ];
                        foreach ($forecast as $f):
                        ?>
                        <div class="flex items-center gap-3">
                            <span class="w-9 text-[10px] font-black text-slate-400 uppercase"><?php echo $f['day']; ?></span>
                            <div class="flex-grow bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div class="bg-[#15803d] h-2 rounded-full" style="width: <?php echo $f['pct']; ?>%;"></div>
                            </div>
                            <span class="w-16 text-right text-xs font-black text-slate-900"><?php echo number_format($f['kwh'], 1); ?> kWh</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-emerald-50/40 border border-emerald-100/60 p-5 rounded-2xl space-y-4">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-lightbulb text-[#15803d]"></i> AI Horizon Insights
                    </h3>
                    <div class="flex gap-2.5 items-start">
                        <span class="text-lg">☀️</span>
                        <p class="text-slate-600 text-[11px] leading-relaxed font-semibold">High solar intensity expected Wednesday between 10 AM–2 PM. Schedule heavy appliances then.</p>
                    </div>
                    <div class="flex gap-2.5 items-start">
                        <span class="text-lg">🔋</span>
                        <p class="text-slate-600 text-[11px] leading-relaxed font-semibold">Battery is projected to reach full charge by Thursday evening under current usage patterns.</p>
                    </div>
                    <div class="flex gap-2.5 items-start">
                        <span class="text-lg">🌧️</span>
                        <p class="text-slate-600 text-[11px] leading-relaxed font-semibold">Lower output expected Friday due to forecasted cloud cover &mdash; consider conserving stored battery power.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

</body>
</html>