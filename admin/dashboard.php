<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_schema.php';
require_once __DIR__ . '/../includes/weather.php';
greenai_ensure_support_tables($pdo);

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php?as=admin");
    exit();
}

$adminName = $_SESSION['admin']['fullname'] ?? 'Support Agent';
$activeNav = 'tickets';

$statusFilter = $_GET['status'] ?? 'all';
$allowedStatuses = ['open', 'in_progress', 'resolved'];

$sql = "SELECT t.id, t.category, t.subject, t.status, t.created_at, u.fullname, u.email
        FROM support_tickets t
        JOIN users u ON u.id = t.user_id";
if (in_array($statusFilter, $allowedStatuses, true)) {
    $sql .= " WHERE t.status = ?";
}
$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(in_array($statusFilter, $allowedStatuses, true) ? [$statusFilter] : []);
$tickets = $stmt->fetchAll();

$statusStyles = [
    'open'        => 'bg-rose-50 text-rose-700 border-rose-100',
    'in_progress' => 'bg-amber-50 text-amber-700 border-amber-100',
    'resolved'    => 'bg-emerald-50 text-emerald-700 border-emerald-100',
];
$statusLabels = [
    'open'        => 'Open',
    'in_progress' => 'In Progress',
    'resolved'    => 'Resolved',
];

// Real-time weather, same feed used across the resident dashboard (see dashboard/weather.php).
$weather = greenai_get_weather();
$hasWeather = empty($weather['error']) && isset($weather['current_weather']);
$current = $hasWeather ? $weather['current_weather'] : null;
$currentInfo = $current ? greenai_weather_code_info($current['weathercode']) : ['label' => 'Unavailable', 'icon' => 'fa-cloud-question'];
$locationLabel = $weather['label'] ?? GREENAI_WEATHER_LABEL;

$hourlyRows = [];
if ($hasWeather && isset($weather['hourly']['time'])) {
    $nowHour = date('Y-m-d\TH:00');
    $startIdx = array_search($nowHour, $weather['hourly']['time']);
    if ($startIdx === false) {
        $startIdx = 0;
    }
    for ($i = $startIdx; $i < min($startIdx + 6, count($weather['hourly']['time'])); $i++) {
        $hourlyRows[] = [
            'time' => $weather['hourly']['time'][$i],
            'temp' => $weather['hourly']['temperature_2m'][$i],
            'code' => $weather['hourly']['weathercode'][$i],
            'pop'  => $weather['hourly']['precipitation_probability'][$i],
        ];
    }
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Console - Dashboard | Green-AI</title>
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
            <div>
                <h2 class="text-lg font-black text-slate-900 tracking-tight">Support Tickets</h2>
                <p class="text-slate-500 text-xs mt-1">Issues residents have reported about their equipment or the website.</p>
            </div>
            <div class="flex items-center flex-wrap gap-1 bg-white p-1 rounded-xl border border-slate-100 shadow-2xs text-[11px] font-bold text-gray-500 w-fit">
                <a href="dashboard.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 <?php echo $activeNav === 'tickets' ? 'bg-[#15803d] text-white' : 'hover:text-slate-700'; ?>"><i class="fa-solid fa-ticket"></i> Support Tickets</a>
                <a href="users.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 <?php echo $activeNav === 'users' ? 'bg-[#15803d] text-white' : 'hover:text-slate-700'; ?>"><i class="fa-solid fa-users"></i> Users</a>
                <a href="site-assessments.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 <?php echo $activeNav === 'site-assessments' ? 'bg-[#15803d] text-white' : 'hover:text-slate-700'; ?>"><i class="fa-solid fa-solar-panel"></i> Site Assessments</a>
                <a href="solar-monitoring.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 <?php echo $activeNav === 'solar-monitoring' ? 'bg-[#15803d] text-white' : 'hover:text-slate-700'; ?>"><i class="fa-solid fa-chart-line"></i> Live Monitoring</a>
            </div>
        </div>

        <div class="bg-gradient-to-br from-[#15803d] to-[#0f4f1b] rounded-3xl p-6 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-emerald-200 flex items-center gap-1.5">
                    <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($locationLabel); ?> &mdash; Real-Time Forecast
                </span>
                <div class="flex items-center gap-4">
                    <i class="fa-solid <?php echo $currentInfo['icon']; ?> text-4xl"></i>
                    <div>
                        <strong class="text-4xl font-black tracking-tight"><?php echo $current ? round($current['temperature']) : '--'; ?>°C</strong>
                        <p class="text-xs font-bold text-emerald-100 mt-1"><?php echo htmlspecialchars($currentInfo['label']); ?></p>
                    </div>
                </div>
            </div>
            <?php if (!empty($hourlyRows)): ?>
            <div class="flex gap-2 overflow-x-auto">
                <?php foreach ($hourlyRows as $h): $info = greenai_weather_code_info($h['code']); ?>
                <div class="flex flex-col items-center gap-1 bg-white/10 border border-white/10 rounded-xl px-3.5 py-2.5 min-w-[68px] shrink-0">
                    <span class="text-[9px] font-black text-emerald-200 uppercase"><?php echo date('h A', strtotime($h['time'])); ?></span>
                    <i class="fa-solid <?php echo $info['icon']; ?> text-sm"></i>
                    <span class="text-xs font-black"><?php echo round($h['temp']); ?>°</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!$hasWeather): ?>
        <div class="bg-amber-50 border border-amber-100 text-amber-700 p-4 rounded-2xl text-xs font-bold">
            <i class="fa-solid fa-triangle-exclamation mr-1.5"></i> Live weather data is temporarily unavailable. Showing the last known reading if one exists.
        </div>
        <?php elseif (!empty($weather['stale'])): ?>
        <div class="bg-amber-50 border border-amber-100 text-amber-700 p-4 rounded-2xl text-xs font-bold">
            <i class="fa-solid fa-clock-rotate-left mr-1.5"></i> Showing cached weather data &mdash; the live feed could not be reached just now.
        </div>
        <?php endif; ?>

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
                height="420"
                frameborder="0"
                loading="lazy"
                title="Live Philippines weather radar"
                class="block"
            ></iframe>
            <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 text-[10px] font-semibold text-slate-400">
                <i class="fa-solid fa-layer-group mr-1"></i> Switch layers (rain, wind, temperature, clouds) directly on the map. Map data via Windy.com.
            </div>
        </div>

        <?php if (!empty($dailyRows)): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-2xs overflow-hidden">
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

            <div class="space-y-5">
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
        </div>
        <?php endif; ?>

        <div class="flex items-center gap-1 bg-gray-50 p-1 rounded-xl border border-gray-100 text-[11px] font-bold text-gray-500 w-fit">
            <a href="?status=all" class="px-3 py-1.5 rounded-lg transition-all <?php echo $statusFilter === 'all' ? 'bg-white text-slate-800 shadow-2xs' : 'hover:text-slate-700'; ?>">All</a>
            <a href="?status=open" class="px-3 py-1.5 rounded-lg transition-all <?php echo $statusFilter === 'open' ? 'bg-white text-slate-800 shadow-2xs' : 'hover:text-slate-700'; ?>">Open</a>
            <a href="?status=in_progress" class="px-3 py-1.5 rounded-lg transition-all <?php echo $statusFilter === 'in_progress' ? 'bg-white text-slate-800 shadow-2xs' : 'hover:text-slate-700'; ?>">In Progress</a>
            <a href="?status=resolved" class="px-3 py-1.5 rounded-lg transition-all <?php echo $statusFilter === 'resolved' ? 'bg-white text-slate-800 shadow-2xs' : 'hover:text-slate-700'; ?>">Resolved</a>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Resident</th>
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Category</th>
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Subject</th>
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Submitted</th>
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="5" class="p-6 text-center text-slate-400 font-bold">No tickets found for this filter.</td>
                        </tr>
                        <?php else: foreach ($tickets as $t): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors cursor-pointer" onclick="window.location.href='ticket.php?id=<?php echo (int)$t['id']; ?>'">
                            <td class="p-4">
                                <span class="block text-slate-900 font-bold"><?php echo htmlspecialchars($t['fullname']); ?></span>
                                <span class="block text-[10px] text-slate-400"><?php echo htmlspecialchars($t['email']); ?></span>
                            </td>
                            <td class="p-4 text-slate-500 capitalize"><?php echo htmlspecialchars($t['category']); ?></td>
                            <td class="p-4 font-bold text-slate-900"><?php echo htmlspecialchars($t['subject']); ?></td>
                            <td class="p-4 text-slate-500"><?php echo htmlspecialchars(date('M j, g:i A', strtotime($t['created_at']))); ?></td>
                            <td class="p-4">
                                <span class="px-2.5 py-1 <?php echo $statusStyles[$t['status']] ?? 'bg-slate-100 text-slate-500 border-slate-200'; ?> text-[10px] font-bold rounded-lg border"><?php echo $statusLabels[$t['status']] ?? htmlspecialchars($t['status']); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</body>
</html>
