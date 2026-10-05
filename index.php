<?php
// 1. LIFELINE SESSION SECURITY GATEWAY
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. SECURITY REDIRECT IF NOT SIGNED IN
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// 3. CURRENT LOGGED-IN USER DATA
$fullname = $_SESSION['user']['fullname'] ?? $_SESSION['fullname'] ?? 'Green-AI User';
$email = $_SESSION['user']['email'] ?? '';


// 4. PER-RESIDENT ENERGY OVERVIEW — each household gets its own auto-provisioned
// rooftop system (deterministic from their user id), so the Energy Overview
// chart below reflects genuinely different, physically-plausible figures per user.
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/solar_monitoring_schema.php';
require_once __DIR__ . '/includes/solar_simulation.php';
greenai_ensure_solar_monitoring_tables($pdo);

$userId = (int)($_SESSION['user']['id'] ?? 0);
greenai_ensure_resident_system($pdo, $userId, $fullname, $_SESSION['user']['residence'] ?? null);
$plantStmt = $pdo->prepare("SELECT * FROM solar_plants WHERE user_id = ?");
$plantStmt->execute([$userId]);
$plant = $plantStmt->fetch();

$energyOverviewChart = greenai_energy_overview_payload($plant);
$energyOverviewChartJson = json_encode($energyOverviewChart, JSON_NUMERIC_CHECK);

$liveFlowNow = greenai_solar_live_flow($plant);
$liveBatteryNow = greenai_chart_battery_soc_at($plant, new DateTimeImmutable('now', new DateTimeZone($plant['timezone_name'] ?: 'Asia/Manila')));
$energyOverviewLiveJson = json_encode([
    'solarKw'       => $liveFlowNow['pv_kw'],
    'consumptionKw' => $liveFlowNow['load_kw'],
    'batteryPct'    => $liveBatteryNow['soc_pct'],
    'hasBattery'    => true,
]);

// Same resident snapshot the Live Monitoring page uses (liveFlow, todayProductionKwh, ...).
extract(greenai_solar_dashboard_snapshot($plant));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green-AI - Smart Energy Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media (max-width: 767px) {
            body { padding-top: 56px !important; }
        }
    </style>
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased flex m-0 p-0">

    <?php include __DIR__ . "/includes/sidebar.php"; ?>

    <div class="flex-grow min-w-0 flex flex-col min-h-screen">
        
        <header class="w-full bg-white border-b border-slate-100 px-8 py-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-chart-line text-[#15803d]"></i> Smart Home Dashboard
                </h1>
                <p class="text-slate-500 text-sm mt-1">Overview of your energy, storage, and AI forecasts with the Green-AI dashboard style.</p>
            </div>
            <div class="inline-flex items-center gap-3 rounded-3xl bg-emerald-50 border border-emerald-100 px-4 py-3 text-xs font-black text-emerald-700">
                <span>Good morning, <?php echo htmlspecialchars(explode(' ', $fullname)[0]); ?>!</span>
                <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-[10px] text-emerald-700 border border-emerald-100">
                    <i class="fa-solid fa-sun"></i> Active
                </span>
            </div>
        </header>

        <main class="p-8 space-y-6 flex-grow overflow-y-auto max-w-7xl w-full mx-auto">
            
            <?php
            $cardBase = 'bg-white p-5 rounded-3xl border border-slate-100 shadow-xs space-y-3';
            $labelCls = 'block text-[11px] font-bold text-slate-400 uppercase tracking-wider';
            $valCls   = 'block text-2xl font-black text-slate-900 tracking-tight';
            $unitCls  = 'text-xs font-black text-gray-400';
            $subCls   = 'flex items-center gap-1.5 text-[11px] font-bold text-gray-400';
            ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="<?php echo $cardBase; ?>">
                    <div class="flex justify-between items-start">
                        <div class="space-y-0.5">
                            <span class="<?php echo $labelCls; ?>">Solar Generation (Today)</span>
                            <strong class="<?php echo $valCls; ?>"><?php echo number_format($todayProductionKwh, 1); ?> <span class="<?php echo $unitCls; ?>">kWh</span></strong>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-amber-50 border border-amber-100 text-amber-500 flex items-center justify-center text-sm"><i class="fa-solid fa-solar-panel"></i></span>
                    </div>
                    <div class="<?php echo $subCls; ?>">From your rooftop system</div>
                </div>

                <div class="<?php echo $cardBase; ?>">
                    <div class="flex justify-between items-start">
                        <div class="space-y-0.5">
                            <span class="<?php echo $labelCls; ?>">Live Solar Output</span>
                            <strong class="<?php echo $valCls; ?>"><?php echo number_format($liveFlow['pv_kw'], 2); ?> <span class="<?php echo $unitCls; ?>">kW</span></strong>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-blue-50 border border-blue-100 text-blue-500 flex items-center justify-center text-sm"><i class="fa-solid fa-bolt"></i></span>
                    </div>
                    <div class="<?php echo $subCls; ?>"><?php echo $liveFlow['pv_kw'] > 0 ? 'Generating now' : 'No sunlight right now'; ?></div>
                </div>

                <div class="<?php echo $cardBase; ?>">
                    <div class="flex justify-between items-start">
                        <div class="space-y-0.5">
                            <span class="<?php echo $labelCls; ?>">House Load</span>
                            <strong class="<?php echo $valCls; ?>"><?php echo number_format($liveFlow['load_kw'], 2); ?> <span class="<?php echo $unitCls; ?>">kW</span></strong>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center text-sm"><i class="fa-solid fa-house-laptop"></i></span>
                    </div>
                    <div class="<?php echo $subCls; ?>">Current home consumption</div>
                </div>

                <div class="<?php echo $cardBase; ?>">
                    <div class="flex justify-between items-start">
                        <div class="space-y-0.5">
                            <span class="<?php echo $labelCls; ?>"><?php echo $liveFlow['grid_kw'] > 0 ? 'Grid Import' : 'Grid Export'; ?></span>
                            <strong class="<?php echo $valCls; ?> text-emerald-600"><?php echo number_format(abs($liveFlow['grid_kw']), 2); ?> <span class="<?php echo $unitCls; ?>">kW</span></strong>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-purple-50 border border-purple-100 text-purple-500 flex items-center justify-center text-sm"><i class="fa-solid fa-tower-broadcast"></i></span>
                    </div>
                    <div class="<?php echo $subCls; ?>"><?php echo $liveFlow['grid_kw'] > 0 ? 'Drawing from the grid' : 'Surplus feeding grid'; ?></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-2 bg-white p-6 rounded-3xl border border-slate-100 shadow-xs space-y-5">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Energy Overview</h3>
                            <p class="text-[10px] font-bold text-slate-400 mt-0.5"><?php echo number_format((float)$plant['capacity_kw'], 2); ?> kWp <?php echo htmlspecialchars(ucwords(str_replace('_', '-', $plant['system_type']))); ?> system &middot; simulated live feed</p>
                        </div>
                        <div class="flex items-center gap-1 bg-gray-50 p-1 rounded-xl border border-gray-100 text-[10px] font-bold text-gray-400">
                            <button onclick="switchChartTimeline(this, 'day')" class="chart-filter-btn bg-white text-slate-800 shadow-3xs px-2.5 py-1 rounded-lg transition-all cursor-pointer">Day</button>
                            <button onclick="switchChartTimeline(this, 'week')" class="chart-filter-btn px-2.5 py-1 hover:text-slate-700 transition-all cursor-pointer">Week</button>
                            <button onclick="switchChartTimeline(this, 'month')" class="chart-filter-btn px-2.5 py-1 hover:text-slate-700 transition-all cursor-pointer">Month</button>
                            <button onclick="switchChartTimeline(this, 'year')" class="chart-filter-btn px-2.5 py-1 hover:text-slate-700 transition-all cursor-pointer">Year</button>
                        </div>
                    </div>

                    <div class="flex gap-2 transition-opacity duration-200 ease-out" id="chartVisualRow">
                        <!-- Left axis: Solar / Consumption scale (kW or kWh depending on tab). The inner div is
                             inset by top-4/bottom-0 to exactly match the svg's own pt-4 content box, so tick
                             percentages line up with the gridlines regardless of container size. -->
                        <div class="relative w-10 h-48 shrink-0">
                            <div class="absolute inset-x-0 top-4 bottom-0" id="chartAxisLeft"></div>
                        </div>

                        <div class="relative flex-grow min-w-0 h-48 pt-4">
                            <svg class="w-full h-full" viewBox="0 0 600 160" preserveAspectRatio="none" id="energyChartSvg">
                                <defs>
                                    <linearGradient id="solarFillGradient" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.22" />
                                        <stop offset="100%" stop-color="#f59e0b" stop-opacity="0" />
                                    </linearGradient>
                                </defs>

                                <line x1="0" y1="8" x2="600" y2="8" stroke="#f1f5f9" stroke-width="1" />
                                <line x1="0" y1="44" x2="600" y2="44" stroke="#f1f5f9" stroke-dasharray="4,4" stroke-width="1" />
                                <line x1="0" y1="80" x2="600" y2="80" stroke="#f1f5f9" stroke-dasharray="4,4" stroke-width="1" />
                                <line x1="0" y1="116" x2="600" y2="116" stroke="#f1f5f9" stroke-dasharray="4,4" stroke-width="1" />
                                <line x1="0" y1="152" x2="600" y2="152" stroke="#e2e8f0" stroke-width="1" />

                                <path id="solarFillArea" fill="url(#solarFillGradient)" stroke="none" class="transition-all duration-500" />
                                <path id="solarCurveLine" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="transition-all duration-500" />
                                <path id="consumptionCurveLine" fill="none" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="transition-all duration-500" />
                                <path id="batteryCurveLine" fill="none" stroke="#10b981" stroke-width="2" stroke-dasharray="5,3" stroke-linecap="round" stroke-linejoin="round" class="transition-all duration-500" />

                                <line id="chartHoverLine" x1="0" y1="8" x2="0" y2="152" stroke="#cbd5e1" stroke-width="1" class="hidden" />
                                <circle id="chartHoverSolarDot" r="4" fill="#f59e0b" stroke="#fff" stroke-width="1.5" class="hidden" />
                                <circle id="chartHoverConsumptionDot" r="4" fill="#3b82f6" stroke="#fff" stroke-width="1.5" class="hidden" />
                                <circle id="chartHoverBatteryDot" r="3.5" fill="#10b981" stroke="#fff" stroke-width="1.5" class="hidden" />

                                <rect id="chartHoverCapture" x="0" y="0" width="600" height="160" fill="transparent" />
                            </svg>

                            <div id="chartTooltip" class="hidden absolute z-10 pointer-events-none bg-slate-900 text-white text-[10px] font-bold rounded-xl px-3 py-2 shadow-lg space-y-1 whitespace-nowrap"></div>

                            <div class="w-full flex justify-between text-[9px] font-bold text-slate-400 pt-2 border-t border-slate-100" id="chartLabelTimelineGrid">
                                <span>12 AM</span><span>4 AM</span><span>8 AM</span><span>12 PM</span><span>4 PM</span><span>8 PM</span><span>12 AM</span>
                            </div>
                        </div>

                        <!-- Right axis: Battery state-of-charge percentage -->
                        <div class="relative w-10 h-48 shrink-0">
                            <div class="absolute inset-x-0 top-4 bottom-0" id="chartAxisRight"></div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[10px] font-bold text-gray-400 pt-1 transition-opacity duration-200 ease-out" id="chartLegendRow">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Solar Generation <strong id="legendSolarValue" class="text-slate-700 font-black">&mdash;</strong></span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Consumption <strong id="legendConsumptionValue" class="text-slate-700 font-black">&mdash;</strong></span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Battery <strong id="legendBatteryValue" class="text-slate-700 font-black">&mdash;</strong></span>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-xs space-y-4 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">AI Energy Prediction</h3>
                            <a href="dashboard/prediction.php" class="text-emerald-700 hover:text-emerald-800 text-[10px] font-black tracking-wide">View Details <i class="fa-solid fa-chevron-right text-[8px] ml-0.5"></i></a>
                        </div>
                        <div class="bg-slate-50 border border-slate-100 p-4 rounded-3xl space-y-1">
                            <span class="block text-[10px] text-gray-400 font-bold uppercase tracking-wide">Tomorrow's Generation Forecast</span>
                            <div class="flex items-baseline gap-1">
                                <strong class="text-xl font-black text-slate-900 tracking-tight">19.2</strong>
                                <span class="text-xs font-bold text-gray-400">kWh</span>
                            </div>
                            <span class="text-[10px] font-bold text-emerald-600 block"><i class="fa-solid fa-arrow-trend-up"></i> 12% higher than today</span>
                        </div>
                    </div>

                    <div class="bg-emerald-50/40 border border-emerald-100/60 p-3.5 rounded-xl flex gap-3 items-start">
                        <span class="text-lg">💡</span>
                        <div class="space-y-0.5">
                            <strong class="block text-xs font-black text-slate-900">AI Horizon Insight</strong>
                            <p class="text-gray-500 text-[10px] leading-relaxed font-semibold">High solar intensity expected tomorrow between 10:00 AM – 2:00 PM. Plan heavy appliance load schedules during this interval.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="alerts-section" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-2 bg-white p-6 rounded-3xl border border-slate-100 shadow-xs space-y-4">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Real-Time Grid Allocation Flow</h3>
                    
                    <div class="grid grid-cols-3 gap-3 text-center text-xs font-bold pt-2">
                        <div class="p-3 bg-amber-50/60 border border-amber-100/60 rounded-xl space-y-1">
                            <i class="fa-solid fa-solar-panel text-amber-500 text-lg"></i>
                            <span class="block text-[10px] text-gray-400">Solar Array Source</span>
                            <span class="block font-black text-slate-800">2.45 kW</span>
                        </div>
                        <div class="p-3 bg-gray-50 border border-gray-100 rounded-xl space-y-1 flex flex-col justify-center items-center">
                            <i class="fa-solid fa-bolt-lightning text-emerald-600 text-lg animate-pulse"></i>
                            <span class="block text-[10px] text-gray-400">Smart Inverter Hub</span>
                            <span class="block font-black text-slate-800">2.30 kW</span>
                        </div>
                        <div class="p-3 bg-blue-50/60 border border-blue-100/60 rounded-xl space-y-1">
                            <i class="fa-solid fa-plug text-blue-500 text-lg"></i>
                            <span class="block text-[10px] text-gray-400">Current House Load</span>
                            <span class="block font-black text-slate-800">1.32 kW</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-xs space-y-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Recent Operational Alerts</h3>
                        <a href="dashboard/monitoring.php" class="text-gray-400 hover:text-slate-600 text-[10px] font-bold">View All</a>
                    </div>

                    <div class="space-y-3 text-[11px] font-bold">
                        <div class="flex items-start gap-2.5 border-b border-gray-50 pb-2.5">
                            <span class="w-2 h-2 rounded-full bg-rose-500 mt-1.5 shrink-0"></span>
                            <div class="space-y-0.5">
                                <p class="text-slate-800 leading-tight">ReadingMeter is reporting Offline &mdash; no active/reactive power or voltage readings available.</p>
                                <span class="block text-[9px] text-gray-400 font-medium">Just now</span>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5 border-b border-gray-50 pb-2.5">
                            <span class="w-2 h-2 rounded-full bg-amber-500 mt-1.5 shrink-0"></span>
                            <div class="space-y-0.5">
                                <p class="text-slate-800 leading-tight">Dust accumulation detected on Rooftop Solar Array &mdash; generation efficiency reduced by ~6%.</p>
                                <span class="block text-[9px] text-gray-400 font-medium">35 mins ago</span>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5 border-b border-gray-50 pb-2.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 mt-1.5 shrink-0"></span>
                            <div class="space-y-0.5">
                                <p class="text-slate-800 leading-tight">System microgrid architectures functioning within normal constraints.</p>
                                <span class="block text-[9px] text-gray-400 font-medium">10 mins ago</span>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5 border-b border-gray-50 pb-2.5">
                            <span class="w-2 h-2 rounded-full bg-amber-500 mt-1.5 shrink-0"></span>
                            <div class="space-y-0.5">
                                <p class="text-slate-800 leading-tight">Peak demand escalation vector noted yesterday at 8:00 PM.</p>
                                <span class="block text-[9px] text-gray-400 font-medium">Yesterday, 8:00 PM</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="w-full text-center py-4 text-[10px] font-bold text-gray-400 tracking-wide border-t border-gray-100 bg-white shrink-0">
            &copy; <?php echo date('Y'); ?> Green-AI Smart Home Framework. All rights reserved.
        </footer>
    </div>

    <script>
    // Real per-resident simulated data (deterministic per user id — see
    // includes/solar_simulation.php) driving the Energy Overview chart.
    const energyChartData = <?php echo $energyOverviewChartJson; ?>;
    const energyChartLive = <?php echo $energyOverviewLiveJson; ?>;

    (function () {
        const PLOT_TOP = 8, PLOT_BOTTOM = 152, PLOT_WIDTH = 600;
        const AXIS_FRACTIONS = [0.05, 0.275, 0.5, 0.725, 0.95]; // matches gridlines at y=8,44,80,116,152

        const svg = document.getElementById('energyChartSvg');
        const capture = document.getElementById('chartHoverCapture');
        const tooltip = document.getElementById('chartTooltip');
        const hoverLine = document.getElementById('chartHoverLine');
        const dotSolar = document.getElementById('chartHoverSolarDot');
        const dotConsumption = document.getElementById('chartHoverConsumptionDot');
        const dotBattery = document.getElementById('chartHoverBatteryDot');
        const axisLeftEl = document.getElementById('chartAxisLeft');
        const axisRightEl = document.getElementById('chartAxisRight');
        const legendSolar = document.getElementById('legendSolarValue');
        const legendConsumption = document.getElementById('legendConsumptionValue');
        const legendBattery = document.getElementById('legendBatteryValue');
        const chartVisualRow = document.getElementById('chartVisualRow');
        const chartLegendRow = document.getElementById('chartLegendRow');

        let currentTimeline = 'day';
        let isTransitioning = false;

        function niceScale(peak) {
            if (!isFinite(peak) || peak <= 0) peak = 1;
            const rough = peak * 1.15;
            const magnitude = Math.pow(10, Math.floor(Math.log10(rough)));
            const residual = rough / magnitude;
            let niceResidual;
            if (residual <= 1) niceResidual = 1;
            else if (residual <= 2) niceResidual = 2;
            else if (residual <= 2.5) niceResidual = 2.5;
            else if (residual <= 5) niceResidual = 5;
            else niceResidual = 10;
            return niceResidual * magnitude;
        }

        function xForIndex(idx, count) {
            return count <= 1 ? PLOT_WIDTH / 2 : (idx / (count - 1)) * PLOT_WIDTH;
        }

        function scaleY(value, axisMax) {
            const clamped = Math.max(0, Math.min(value || 0, axisMax));
            return PLOT_BOTTOM - (clamped / axisMax) * (PLOT_BOTTOM - PLOT_TOP);
        }

        function smoothPath(points) {
            if (points.length === 0) return '';
            if (points.length === 1) return `M ${points[0][0]} ${points[0][1]}`;
            let d = `M ${points[0][0]} ${points[0][1]}`;
            for (let i = 0; i < points.length - 1; i++) {
                const p0 = points[i === 0 ? i : i - 1];
                const p1 = points[i];
                const p2 = points[i + 1];
                const p3 = points[i + 2 < points.length ? i + 2 : i + 1];
                const cp1x = p1[0] + (p2[0] - p0[0]) / 6;
                const cp1y = p1[1] + (p2[1] - p0[1]) / 6;
                const cp2x = p2[0] - (p3[0] - p1[0]) / 6;
                const cp2y = p2[1] - (p3[1] - p1[1]) / 6;
                d += ` C ${cp1x.toFixed(2)} ${cp1y.toFixed(2)}, ${cp2x.toFixed(2)} ${cp2y.toFixed(2)}, ${p2[0].toFixed(2)} ${p2[1].toFixed(2)}`;
            }
            return d;
        }

        function fmtNice(v) {
            const rounded = Math.round(v * 100) / 100;
            return rounded % 1 === 0 ? rounded.toString() : rounded.toFixed(1);
        }

        function fmtValue(v, unit) {
            const n = v || 0;
            return (n >= 10 ? n.toFixed(1) : n.toFixed(2)) + ' ' + unit;
        }

        function renderAxisColumn(el, values, formatter, alignRight) {
            el.innerHTML = AXIS_FRACTIONS.map((frac, i) => {
                const alignClass = alignRight ? 'right-1 text-right' : 'left-1 text-left';
                return `<span class="absolute ${alignClass} text-[9px] font-bold text-slate-400" style="top:${frac * 100}%; transform:translateY(-50%);">${formatter(values[i])}</span>`;
            }).join('');
        }

        function renderRightAxis(hasBattery) {
            if (hasBattery) {
                renderAxisColumn(axisRightEl, [100, 75, 50, 25, 0], v => v + '%', false);
            } else {
                axisRightEl.innerHTML = '<span class="absolute inset-0 flex items-center justify-center text-center text-[8px] font-black text-slate-300 tracking-widest leading-tight px-0.5">NO<br>BATTERY</span>';
            }
        }

        function hideHover() {
            tooltip.classList.add('hidden');
            hoverLine.classList.add('hidden');
            dotSolar.classList.add('hidden');
            dotConsumption.classList.add('hidden');
            dotBattery.classList.add('hidden');
        }

        function renderChart(timelineKey) {
            currentTimeline = timelineKey;
            const data = energyChartData[timelineKey];
            const count = data.solar.length;
            const scaleMax = niceScale(Math.max(...data.solar, ...data.consumption, 0));

            const solarPts = data.solar.map((v, i) => [xForIndex(i, count), scaleY(v, scaleMax)]);
            const consumptionPts = data.consumption.map((v, i) => [xForIndex(i, count), scaleY(v, scaleMax)]);
            const batteryPts = data.battery.map((v, i) => [xForIndex(i, count), scaleY(v, 100)]);

            const solarPath = smoothPath(solarPts);
            document.getElementById('solarCurveLine').setAttribute('d', solarPath);
            document.getElementById('consumptionCurveLine').setAttribute('d', smoothPath(consumptionPts));

            const batteryLine = document.getElementById('batteryCurveLine');
            if (data.hasBattery) {
                batteryLine.setAttribute('d', smoothPath(batteryPts));
                batteryLine.style.opacity = '1';
            } else {
                batteryLine.setAttribute('d', `M 0 ${PLOT_BOTTOM} L ${PLOT_WIDTH} ${PLOT_BOTTOM}`);
                batteryLine.style.opacity = '0.3';
            }

            const firstX = solarPts.length ? solarPts[0][0] : 0;
            const lastX = solarPts.length ? solarPts[solarPts.length - 1][0] : PLOT_WIDTH;
            document.getElementById('solarFillArea').setAttribute('d', solarPath ? `${solarPath} L ${lastX} ${PLOT_BOTTOM} L ${firstX} ${PLOT_BOTTOM} Z` : '');

            renderAxisColumn(axisLeftEl, [scaleMax, scaleMax * 0.75, scaleMax * 0.5, scaleMax * 0.25, 0], v => fmtNice(v), true);
            renderRightAxis(data.hasBattery);

            document.getElementById('chartLabelTimelineGrid').innerHTML = data.xLabels.map(l => `<span>${l}</span>`).join('');

            if (timelineKey === 'day') {
                legendSolar.textContent = fmtValue(energyChartLive.solarKw, 'kW') + ' now';
                legendConsumption.textContent = fmtValue(energyChartLive.consumptionKw, 'kW') + ' now';
                legendBattery.textContent = energyChartLive.hasBattery ? energyChartLive.batteryPct + '% now' : 'No battery';
            } else {
                const lastIdx = count - 1;
                legendSolar.textContent = fmtValue(data.solar[lastIdx], data.unit) + ' latest';
                legendConsumption.textContent = fmtValue(data.consumption[lastIdx], data.unit) + ' latest';
                legendBattery.textContent = data.hasBattery ? data.battery[lastIdx] + '% latest' : 'No battery';
            }

            hideHover();
        }

        capture.addEventListener('mousemove', (e) => {
            const data = energyChartData[currentTimeline];
            const count = data.solar.length;
            if (count === 0) return;

            const pt = svg.createSVGPoint();
            pt.x = e.clientX;
            pt.y = e.clientY;
            const svgPt = pt.matrixTransform(svg.getScreenCTM().inverse());
            let idx = Math.round((svgPt.x / PLOT_WIDTH) * (count - 1));
            idx = Math.max(0, Math.min(count - 1, idx));

            const scaleMax = niceScale(Math.max(...data.solar, ...data.consumption, 0));
            const x = xForIndex(idx, count);
            const ySolar = scaleY(data.solar[idx], scaleMax);
            const yConsumption = scaleY(data.consumption[idx], scaleMax);

            hoverLine.setAttribute('x1', x);
            hoverLine.setAttribute('x2', x);
            hoverLine.classList.remove('hidden');
            dotSolar.setAttribute('cx', x); dotSolar.setAttribute('cy', ySolar); dotSolar.classList.remove('hidden');
            dotConsumption.setAttribute('cx', x); dotConsumption.setAttribute('cy', yConsumption); dotConsumption.classList.remove('hidden');

            if (data.hasBattery) {
                dotBattery.setAttribute('cx', x);
                dotBattery.setAttribute('cy', scaleY(data.battery[idx], 100));
                dotBattery.classList.remove('hidden');
            } else {
                dotBattery.classList.add('hidden');
            }

            const label = (data.pointLabels && data.pointLabels[idx]) || '';
            tooltip.innerHTML = `
                <div class="text-slate-300 font-black">${label}</div>
                <div class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block"></span>Solar: ${fmtValue(data.solar[idx], data.unit)}</div>
                <div class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-blue-400 inline-block"></span>Consumption: ${fmtValue(data.consumption[idx], data.unit)}</div>
                <div class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span>Battery: ${data.hasBattery ? data.battery[idx] + '%' : 'Not installed'}</div>
            `;
            tooltip.classList.remove('hidden');

            const containerRect = capture.closest('.relative').getBoundingClientRect();
            let left = e.clientX - containerRect.left + 14;
            const top = e.clientY - containerRect.top - 10;
            if (left > containerRect.width - 150) {
                left = e.clientX - containerRect.left - 160;
            }
            tooltip.style.left = Math.max(0, left) + 'px';
            tooltip.style.top = Math.max(0, top) + 'px';
        });

        capture.addEventListener('mouseleave', hideHover);

        window.switchChartTimeline = function (buttonElement, viewMode) {
            if (viewMode === currentTimeline || isTransitioning) return;
            isTransitioning = true;

            document.querySelectorAll('.chart-filter-btn').forEach(btn => {
                btn.className = 'chart-filter-btn px-2.5 py-1 hover:text-slate-700 transition-all cursor-pointer';
            });
            buttonElement.className = 'chart-filter-btn bg-white text-slate-800 shadow-3xs px-2.5 py-1 rounded-lg transition-all cursor-pointer';

            // Fade the chart out, swap the underlying data while invisible, then fade
            // back in — avoids the instant snap you'd get from redrawing the SVG path
            // live, since day/week/month/year each have a different point count and
            // can't be smoothly morphed between.
            chartVisualRow.style.opacity = '0';
            chartLegendRow.style.opacity = '0';

            window.setTimeout(() => {
                renderChart(viewMode);
                chartVisualRow.style.opacity = '1';
                chartLegendRow.style.opacity = '1';
                window.setTimeout(() => { isTransitioning = false; }, 200);
            }, 180);
        };

        renderChart('day');
    })();
    </script>
</body>
</html>