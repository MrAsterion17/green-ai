<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$current = $hasWeather ? $weather['current_weather'] : null;
$currentInfo = $current ? greenai_weather_code_info($current['weathercode']) : ['label' => 'Unavailable', 'icon' => 'fa-cloud-question'];

// Build the next 8 hourly readings starting from the current hour
$hourlyRows = [];
if ($hasWeather && isset($weather['hourly']['time'])) {
    $nowHour = date('Y-m-d\TH:00');
    $startIdx = array_search($nowHour, $weather['hourly']['time']);
    if ($startIdx === false) {
        $startIdx = 0;
    }
    for ($i = $startIdx; $i < min($startIdx + 8, count($weather['hourly']['time'])); $i++) {
        $hourlyRows[] = [
            'time' => $weather['hourly']['time'][$i],
            'temp' => $weather['hourly']['temperature_2m'][$i],
            'code' => $weather['hourly']['weathercode'][$i],
            'pop'  => $weather['hourly']['precipitation_probability'][$i],
        ];
    }
}

// Build the 7-day outlook
$dailyRows = [];
if ($hasWeather && isset($weather['daily']['time'])) {
    foreach ($weather['daily']['time'] as $i => $dateStr) {
        $dailyRows[] = [
            'date' => $dateStr,
            'max'  => $weather['daily']['temperature_2m_max'][$i],
            'min'  => $weather['daily']['temperature_2m_min'][$i],
            'code' => $weather['daily']['weathercode'][$i],
            'pop'  => $weather['daily']['precipitation_probability_max'][$i],
            'sunrise' => $weather['daily']['sunrise'][$i] ?? null,
            'sunset'  => $weather['daily']['sunset'][$i] ?? null,
        ];
    }
}

$locationLabel = $weather['label'] ?? GREENAI_WEATHER_LABEL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-time Forecasting - Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased flex m-0 p-0">

    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow min-w-0 flex flex-col min-h-screen">

        <header class="w-full bg-white border-b border-gray-100 px-8 py-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-cloud-sun text-[#15803d]"></i> Real-time Forecasting
                </h1>
                <p class="text-gray-400 text-xs font-semibold mt-0.5">Live atmospheric telemetry for the Philippines, refreshed automatically.</p>
            </div>
            <div class="flex items-center gap-3 text-xs font-bold text-slate-600">
                <div class="flex items-center gap-2 bg-gray-50 border border-gray-100 px-3 py-1.5 rounded-xl">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-emerald-600 font-black">LIVE</span>
                    <span class="text-gray-400 text-[10px] font-bold">Updated <?php echo $hasWeather ? date('h:i A', strtotime($weather['current_weather']['time'])) : '--'; ?></span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-2xl bg-emerald-50 border border-emerald-100 px-3 py-2 text-xs font-black text-emerald-700">
                    <i class="fa-solid fa-circle-user"></i> <?php echo htmlspecialchars($fullname); ?>
                </div>
            </div>
        </header>

        <main class="p-8 space-y-6 flex-grow overflow-y-auto max-w-6xl w-full">

            <?php if (!$hasWeather): ?>
            <div class="bg-amber-50 border border-amber-100 text-amber-700 p-4 rounded-2xl text-xs font-bold">
                <i class="fa-solid fa-triangle-exclamation mr-1.5"></i> Live weather data is temporarily unavailable. Showing the last known reading if one exists.
            </div>
            <?php elseif (!empty($weather['stale'])): ?>
            <div class="bg-amber-50 border border-amber-100 text-amber-700 p-4 rounded-2xl text-xs font-bold">
                <i class="fa-solid fa-clock-rotate-left mr-1.5"></i> Showing cached weather data &mdash; the live feed could not be reached just now.
            </div>
            <?php endif; ?>

            <div class="bg-gradient-to-br from-[#15803d] to-[#0f4f1b] rounded-3xl p-8 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-emerald-200 flex items-center gap-1.5">
                        <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($locationLabel); ?>
                    </span>
                    <div class="flex items-center gap-4">
                        <i class="fa-solid <?php echo $currentInfo['icon']; ?> text-5xl"></i>
                        <div>
                            <strong class="text-5xl font-black tracking-tight"><?php echo $current ? round($current['temperature']) : '--'; ?>°C</strong>
                            <p class="text-sm font-bold text-emerald-100 mt-1"><?php echo htmlspecialchars($currentInfo['label']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 text-xs font-bold">
                    <div class="bg-white/10 border border-white/10 rounded-xl p-3 space-y-0.5">
                        <span class="block text-[10px] uppercase text-emerald-200">Wind Speed</span>
                        <span class="block text-lg font-black"><?php echo $current ? $current['windspeed'] : '--'; ?> km/h</span>
                    </div>
                    <div class="bg-white/10 border border-white/10 rounded-xl p-3 space-y-0.5">
                        <span class="block text-[10px] uppercase text-emerald-200">Last Updated</span>
                        <span class="block text-lg font-black"><?php echo $current ? date('h:i A', strtotime($current['time'])) : '--'; ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs overflow-hidden">
                <div class="p-5 border-b border-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-satellite-dish text-[#15803d]"></i> Regional Weather Radar
                        </h3>
                        <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Live precipitation, satellite, and wind layers across the Philippines.</p>
                    </div>
                    <a href="https://www.windy.com/-Rain-thunder-rain?rain,12.880,121.774,6" target="_blank" rel="noopener" class="text-[10px] font-black text-emerald-700 hover:text-emerald-800 flex items-center gap-1 shrink-0">
                        Open Full Map <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                    </a>
                </div>
                <iframe
                    src="https://embed.windy.com/embed2.html?lat=12.880&lon=121.774&detailLat=12.880&detailLon=121.774&zoom=6&level=surface&overlay=rain&menu=&message=true&marker=&calendar=now&pressure=&type=map&location=coordinates&detail=&metricWind=default&metricTemp=default&radarRange=-1"
                    width="100%"
                    height="480"
                    frameborder="0"
                    loading="lazy"
                    title="Live Philippines weather radar"
                    class="block"
                ></iframe>
                <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 text-[10px] font-semibold text-slate-400">
                    <i class="fa-solid fa-layer-group mr-1"></i> Switch layers (rain, wind, temperature, clouds) directly on the map. Map data via Windy.com.
                </div>
            </div>

            <?php if (!empty($hourlyRows)): ?>
            <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs p-5">
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider mb-4">Next 8 Hours</h3>
                <div class="flex gap-3 overflow-x-auto pb-1">
                    <?php foreach ($hourlyRows as $h): $info = greenai_weather_code_info($h['code']); ?>
                    <div class="flex flex-col items-center gap-1.5 bg-slate-50 border border-slate-100 rounded-2xl px-4 py-3 min-w-[84px] shrink-0">
                        <span class="text-[10px] font-black text-slate-400 uppercase"><?php echo date('h A', strtotime($h['time'])); ?></span>
                        <i class="fa-solid <?php echo $info['icon']; ?> text-amber-500 text-lg"></i>
                        <span class="text-sm font-black text-slate-900"><?php echo round($h['temp']); ?>°</span>
                        <span class="text-[9px] font-bold text-blue-500"><i class="fa-solid fa-droplet"></i> <?php echo $h['pop']; ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($dailyRows)): ?>
            <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs overflow-hidden">
                <div class="p-5 border-b border-slate-50">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">7-Day Forecast</h3>
                    <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Daily highs, lows, and rain probability for <?php echo htmlspecialchars($locationLabel); ?>.</p>
                </div>
                <div class="divide-y divide-slate-50">
                    <?php foreach ($dailyRows as $i => $d): $info = greenai_weather_code_info($d['code']); ?>
                    <div class="flex items-center justify-between gap-3 p-4">
                        <span class="w-24 text-xs font-black text-slate-700"><?php echo $i === 0 ? 'Today' : date('D, M j', strtotime($d['date'])); ?></span>
                        <div class="flex items-center gap-2 flex-grow">
                            <i class="fa-solid <?php echo $info['icon']; ?> text-amber-500"></i>
                            <span class="text-xs font-semibold text-slate-500"><?php echo htmlspecialchars($info['label']); ?></span>
                        </div>
                        <span class="text-[10px] font-bold text-blue-500 w-14 text-right"><i class="fa-solid fa-droplet"></i> <?php echo $d['pop']; ?>%</span>
                        <span class="text-xs font-black text-slate-900 w-20 text-right"><?php echo round($d['max']); ?>° / <?php echo round($d['min']); ?>°</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-amber-50 border border-amber-100 text-amber-500 flex items-center justify-center"><i class="fa-solid fa-sun"></i></span>
                    <div>
                        <span class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Sunrise Today</span>
                        <span class="block text-sm font-black text-slate-900"><?php echo $dailyRows[0]['sunrise'] ? date('h:i A', strtotime($dailyRows[0]['sunrise'])) : '--'; ?></span>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-500 flex items-center justify-center"><i class="fa-solid fa-moon"></i></span>
                    <div>
                        <span class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Sunset Today</span>
                        <span class="block text-sm font-black text-slate-900"><?php echo $dailyRows[0]['sunset'] ? date('h:i A', strtotime($dailyRows[0]['sunset'])) : '--'; ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-emerald-50/40 border border-emerald-100/60 p-4 rounded-2xl flex gap-3 items-start">
                <span class="text-lg">📡</span>
                <p class="text-slate-600 text-[11px] leading-relaxed font-semibold">Powered by Open-Meteo. Data refreshes automatically every 15 minutes and feeds the AI thresholds used across your Green-AI dashboard.</p>
            </div>
        </main>
    </div>

</body>
</html>