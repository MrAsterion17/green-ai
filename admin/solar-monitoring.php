<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_schema.php';
require_once __DIR__ . '/../includes/solar_monitoring_schema.php';
greenai_ensure_support_tables($pdo);
greenai_ensure_solar_monitoring_tables($pdo);

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php?as=admin");
    exit();
}

$adminName = $_SESSION['admin']['fullname'] ?? 'Support Agent';
$activeNav = 'solar-monitoring';

greenai_ensure_all_resident_systems($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Solar Monitoring - Support Console | Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
    <style>
        @keyframes flowMove {
            from { background-position: 0 0; }
            to   { background-position: 24px 0; }
        }
        .flow-track {
            background-image: repeating-linear-gradient(90deg, currentColor 0 8px, transparent 8px 18px);
            animation: flowMove 0.9s linear infinite;
        }
        .flow-track.flow-reverse { animation-direction: reverse; }
        .flow-track.flow-idle { animation-play-state: paused; opacity: 0.25; }
        @keyframes skeletonPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .skeleton { animation: skeletonPulse 1.4s ease-in-out infinite; }
    </style>
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
            <a href="../logout.php" class="text-white/80 hover:text-rose-200 transition-colors text-xs" title="Sign Out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </header>

    <main class="p-4 sm:p-8 max-w-6xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-black text-slate-900 tracking-tight">Live Solar Monitoring</h2>
                <p class="text-slate-500 text-xs mt-1">Real-time and historical performance for every resident's home solar system.</p>
            </div>
            <div class="flex items-center gap-1 bg-white p-1 rounded-xl border border-slate-100 shadow-2xs text-[11px] font-bold text-gray-500 w-fit flex-wrap">
                <a href="dashboard.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 hover:text-slate-700"><i class="fa-solid fa-ticket"></i> Support Tickets</a>
                <a href="users.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 hover:text-slate-700"><i class="fa-solid fa-users"></i> Users</a>
                <a href="site-assessments.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 hover:text-slate-700"><i class="fa-solid fa-solar-panel"></i> Site Assessments</a>
                <a href="solar-monitoring.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 <?php echo $activeNav === 'solar-monitoring' ? 'bg-[#15803d] text-white' : 'hover:text-slate-700'; ?>"><i class="fa-solid fa-chart-line"></i> Live Monitoring</a>
            </div>
        </div>

        <div id="offlineBanner" class="hidden bg-amber-50 border border-amber-100 text-amber-700 p-4 rounded-2xl text-xs font-bold">
            <i class="fa-solid fa-triangle-exclamation mr-1.5"></i> Live data source unreachable right now &mdash; showing the last known reading.
        </div>

        <!-- Resident Selector -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs p-5 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0 flex-grow">
                    <span class="w-11 h-11 rounded-xl bg-emerald-50 border border-emerald-100 text-[#15803d] flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-house-signal"></i></span>
                    <div class="min-w-0 flex-grow">
                        <select id="plantSelect" class="w-full max-w-sm text-sm font-black text-slate-900 bg-transparent border-0 outline-hidden cursor-pointer p-0"></select>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-[10px] font-bold text-slate-400">
                            <span id="plantResidentEmail"></span>
                            <span><i class="fa-solid fa-gauge-high mr-1 text-slate-300"></i><span id="plantCapacity">-- kW</span> rated</span>
                            <span id="plantSystemType" class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded-md capitalize">--</span>
                            <span id="plantLocation"></span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span id="liveIndicator" class="w-2 h-2 rounded-full bg-slate-300"></span>
                    <span id="liveIndicatorLabel" class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Connecting&hellip;</span>
                </div>
            </div>
            <div class="flex items-center gap-2 border-t border-slate-50 pt-3">
                <i class="fa-solid fa-magnifying-glass text-slate-300 text-xs"></i>
                <input type="text" id="residentSearch" placeholder="Search residents by name or email&hellip;" class="flex-grow text-xs font-semibold text-slate-700 outline-hidden bg-transparent placeholder:text-slate-400 placeholder:font-medium">
                <span id="residentCount" class="text-[10px] font-black text-slate-400 shrink-0"></span>
            </div>
        </div>

        <!-- System Flow Diagram -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-diagram-project text-[#15803d]"></i> System Power Flow
                </h3>
                <span class="text-[10px] font-semibold text-slate-400" id="flowUpdatedAt">Updating&hellip;</span>
            </div>

            <div class="flex items-center justify-between gap-1 sm:gap-4">
                <div class="flex flex-col items-center gap-2 w-24 sm:w-28 shrink-0 text-center">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-emerald-50 border-2 border-emerald-200 text-emerald-600 flex items-center justify-center text-xl sm:text-2xl"><i class="fa-solid fa-solar-panel"></i></div>
                    <span class="text-[10px] font-black text-slate-700 uppercase tracking-wide">PV Array</span>
                    <span id="pvPower" class="text-sm font-black text-emerald-700">-- kW</span>
                </div>

                <div class="flex-grow h-2 rounded-full text-emerald-500 flow-track flow-idle" id="trackPvGrid"></div>

                <div class="flex flex-col items-center gap-2 w-28 sm:w-32 shrink-0 text-center">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-sky-50 border-2 border-sky-200 text-sky-600 flex items-center justify-center text-xl sm:text-2xl"><i class="fa-solid fa-plug-circle-bolt"></i></div>
                    <span class="text-[10px] font-black text-slate-700 uppercase tracking-wide">Grid / Meter</span>
                    <span id="gridPower" class="text-sm font-black text-sky-700">-- kW</span>
                    <span id="gridDirection" class="px-2 py-0.5 rounded-md text-[9px] font-bold bg-slate-100 text-slate-500">--</span>
                </div>

                <div class="flex-grow h-2 rounded-full text-amber-500 flow-track flow-idle" id="trackGridLoad"></div>

                <div class="flex flex-col items-center gap-2 w-24 sm:w-28 shrink-0 text-center">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-amber-50 border-2 border-amber-200 text-amber-600 flex items-center justify-center text-xl sm:text-2xl"><i class="fa-solid fa-house-chimney"></i></div>
                    <span class="text-[10px] font-black text-slate-700 uppercase tracking-wide">Load</span>
                    <span id="loadPower" class="text-sm font-black text-amber-700">-- kW</span>
                </div>
            </div>
        </div>

        <!-- Battery Reserve -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs p-5 flex items-center gap-4">
            <span class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-battery-three-quarters"></i></span>
            <div class="flex-grow min-w-0">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Battery Reserve</p>
                <div class="flex items-center gap-2 mt-0.5">
                    <strong id="batteryPct" class="text-xl font-black text-slate-900">&mdash;</strong>
                    <span id="batteryStatus" class="px-2 py-0.5 rounded-md text-[9px] font-bold bg-slate-100 text-slate-500">&mdash;</span>
                </div>
            </div>
            <div class="w-32 shrink-0 hidden sm:block">
                <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                    <div id="batteryBar" class="bg-emerald-500 h-2 rounded-full transition-all duration-500" style="width:0%;"></div>
                </div>
            </div>
        </div>

        <!-- Time-Range Tabs -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex items-center gap-1 bg-gray-50 p-1 rounded-xl border border-gray-100 text-[11px] font-bold text-gray-500 w-fit flex-wrap" id="rangeTabs">
                <button data-range="day" class="range-tab px-3 py-1.5 rounded-lg transition-all">Day</button>
                <button data-range="week" class="range-tab px-3 py-1.5 rounded-lg transition-all">Week</button>
                <button data-range="month" class="range-tab px-3 py-1.5 rounded-lg transition-all">Month</button>
                <button data-range="year" class="range-tab px-3 py-1.5 rounded-lg transition-all">Year</button>
                <button data-range="lifetime" class="range-tab px-3 py-1.5 rounded-lg transition-all">Lifetime</button>
                <button data-range="custom" class="range-tab px-3 py-1.5 rounded-lg transition-all">Custom</button>
            </div>
            <div id="customRangeRow" class="hidden items-center gap-2 text-[11px] font-bold text-slate-500">
                <input type="date" id="customStart" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                <span>to</span>
                <input type="date" id="customEnd" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                <button id="applyCustom" class="bg-[#1b5e20] hover:bg-[#144517] text-white px-3 py-1.5 rounded-lg transition-all">Apply</button>
            </div>
        </div>

        <!-- Summary Metrics Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Production</p>
                <h3 class="text-xl font-black text-slate-900 mt-1" id="metricProduction">&mdash;</h3>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Consumption</p>
                <h3 class="text-xl font-black text-slate-900 mt-1" id="metricConsumption">&mdash;</h3>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Net Energy</p>
                <h3 class="text-xl font-black mt-1" id="metricNetEnergy">&mdash;</h3>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Net Revenue</p>
                    <button id="setTariffBtn" class="text-[9px] font-black text-emerald-700 hover:text-emerald-800 whitespace-nowrap">Set tariff</button>
                </div>
                <h3 class="text-xl font-black text-slate-900 mt-1" id="metricNetRevenue">&mdash;</h3>
                <p class="text-[9px] font-semibold text-slate-400 mt-0.5">at <span id="metricTariff">--</span>/kWh</p>
            </div>
        </div>

        <!-- Power Curve Chart -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-chart-area text-[#15803d]"></i> Power Curve
                </h3>
                <span class="text-[10px] font-semibold text-slate-400" id="chartRangeLabel"></span>
            </div>
            <div class="relative" style="height:320px;">
                <canvas id="powerChart"></canvas>
                <div id="chartSkeleton" class="skeleton absolute inset-0 bg-slate-100 rounded-xl"></div>
            </div>
        </div>

        <!-- Environmental Impact Panel -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-cloud"></i></span>
                <div>
                    <span class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">CO&#8322; Reduction</span>
                    <span class="block text-lg font-black text-slate-900" id="envCo2">&mdash;</span>
                </div>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 text-slate-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-mound"></i></span>
                <div>
                    <span class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Standard Coal Saved</span>
                    <span class="block text-lg font-black text-slate-900" id="envCoal">&mdash;</span>
                </div>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-lime-50 border border-lime-100 text-lime-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-tree"></i></span>
                <div>
                    <span class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Equivalent Trees Planted</span>
                    <span class="block text-lg font-black text-slate-900" id="envTrees">&mdash;</span>
                </div>
            </div>
        </div>

    </main>

    <!-- Set Tariff Modal -->
    <div id="tariffModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center hidden opacity-0 transition-opacity duration-200">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xl max-w-sm w-full mx-4 transform scale-95 transition-transform duration-200">
            <div class="flex items-center gap-3.5 mb-4">
                <div class="w-10 h-10 bg-emerald-50 text-[#15803d] rounded-xl flex items-center justify-center text-md shrink-0">
                    <i class="fa-solid fa-coins"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-900 m-0 leading-tight">Set Electricity Tariff</h3>
                    <p class="text-[11px] font-semibold text-slate-400 mt-1">Used to calculate Net Revenue for this plant.</p>
                </div>
            </div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide">Rate (currency / kWh)</label>
            <input type="number" id="tariffInput" step="0.0001" min="0" class="w-full border border-gray-200 focus:border-[#2e7d32] focus:ring-3 focus:ring-green-700/5 rounded-xl px-4 py-2.5 text-sm outline-hidden transition-all bg-white font-bold text-slate-800 mb-4">
            <p id="tariffError" class="hidden text-[11px] font-bold text-rose-600 mb-3"></p>
            <div class="flex gap-2 justify-end pt-1">
                <button onclick="toggleTariffModal(false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-xl transition-all cursor-pointer border-0">Cancel</button>
                <button id="saveTariffBtn" class="px-4 py-2 bg-[#1b5e20] hover:bg-[#144517] text-white text-xs font-bold rounded-xl transition-all cursor-pointer border-0">Save</button>
            </div>
        </div>
    </div>

<script>
(function () {
    const API = '../api/solarmonitoring.php';
    const RANGE_LABELS = { day: 'Today', week: 'Last 7 Days', month: 'Last 30 Days', year: 'Last 12 Months', lifetime: 'Since Commissioning', custom: 'Custom Range' };
    const SYSTEM_TYPE_LABELS = { grid_tied: 'Grid-Tied', hybrid: 'Hybrid', off_grid: 'Off-Grid' };

    let plants = [];
    let currentPlant = null;
    let currentRange = 'day';
    let liveTimer = null;
    let powerChart = null;

    const el = (id) => document.getElementById(id);
    const fmtKwh = (v) => (v === null || v === undefined || isNaN(v)) ? '--' : Number(v).toLocaleString(undefined, { maximumFractionDigits: 1 }) + ' kWh';
    const fmtKw = (v) => (v === null || v === undefined || isNaN(v)) ? '--' : Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' kW';
    const fmtMoney = (v) => (v === null || v === undefined || isNaN(v)) ? '--' : Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function showOffline(show) {
        el('offlineBanner').classList.toggle('hidden', !show);
    }

    function setLiveIndicator(state) {
        const dot = el('liveIndicator');
        const label = el('liveIndicatorLabel');
        dot.className = 'w-2 h-2 rounded-full';
        if (state === 'live') {
            dot.classList.add('bg-emerald-500');
            label.textContent = 'Live';
        } else if (state === 'offline') {
            dot.classList.add('bg-rose-400');
            label.textContent = 'Offline';
        } else {
            dot.classList.add('bg-slate-300');
            label.textContent = 'Connecting…';
        }
    }

    async function fetchJson(url, opts) {
        const res = await fetch(url, opts);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.error || 'Request failed');
        }
        return data;
    }

    function renderPlantHeader() {
        if (!currentPlant) return;
        el('plantResidentEmail').textContent = currentPlant.resident_email || '';
        el('plantCapacity').textContent = Number(currentPlant.capacity_kw).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' kW';
        el('plantSystemType').textContent = SYSTEM_TYPE_LABELS[currentPlant.system_type] || currentPlant.system_type;
        el('plantLocation').textContent = currentPlant.location_label ? ('· ' + currentPlant.location_label) : '';
    }

    function filteredPlants() {
        const q = el('residentSearch').value.trim().toLowerCase();
        if (!q) return plants;
        return plants.filter((p) => (p.resident_name || '').toLowerCase().includes(q) || (p.resident_email || '').toLowerCase().includes(q));
    }

    function populatePlantSelect(list) {
        const select = el('plantSelect');
        select.innerHTML = '';
        list.forEach((p) => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.resident_name + ' — ' + p.name;
            select.appendChild(opt);
        });
        el('residentCount').textContent = list.length + (list.length === 1 ? ' resident' : ' residents');
    }

    function setFlowTrack(trackId, powerKw, capacityKw, reverse) {
        const track = el(trackId);
        const ratio = capacityKw > 0 ? Math.min(1, Math.abs(powerKw) / capacityKw) : 0;
        const idle = Math.abs(powerKw) < 0.05;
        track.classList.toggle('flow-idle', idle);
        track.classList.toggle('flow-reverse', !!reverse && !idle);
        track.style.height = (4 + ratio * 8) + 'px';
        track.style.animationDuration = idle ? '0.9s' : (1.1 - ratio * 0.7) + 's';
    }

    async function loadLive() {
        if (!currentPlant) return;
        try {
            const data = await fetchJson(`${API}?action=live&plant_id=${currentPlant.id}`);
            const flow = data.flow;
            const capacity = Number(currentPlant.capacity_kw);

            el('pvPower').textContent = fmtKw(flow.pv_kw);
            el('loadPower').textContent = fmtKw(flow.load_kw);
            el('gridPower').textContent = fmtKw(Math.abs(flow.grid_kw));

            const dirEl = el('gridDirection');
            if (Math.abs(flow.grid_kw) < 0.05) {
                dirEl.textContent = 'Balanced';
                dirEl.className = 'px-2 py-0.5 rounded-md text-[9px] font-bold bg-slate-100 text-slate-500';
            } else if (flow.grid_kw > 0) {
                dirEl.textContent = 'Importing';
                dirEl.className = 'px-2 py-0.5 rounded-md text-[9px] font-bold bg-amber-50 text-amber-700 border border-amber-100';
            } else {
                dirEl.textContent = 'Exporting';
                dirEl.className = 'px-2 py-0.5 rounded-md text-[9px] font-bold bg-sky-50 text-sky-700 border border-sky-100';
            }

            setFlowTrack('trackPvGrid', flow.pv_kw, capacity, false);
            setFlowTrack('trackGridLoad', flow.load_kw, capacity, false);

            if (data.battery) {
                el('batteryPct').textContent = data.battery.soc_pct + '%';
                el('batteryBar').style.width = data.battery.soc_pct + '%';
                const statusEl = el('batteryStatus');
                statusEl.textContent = data.battery.charging ? 'Charging' : 'Discharging';
                statusEl.className = 'px-2 py-0.5 rounded-md text-[9px] font-bold ' + (data.battery.charging ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-amber-50 text-amber-700 border border-amber-100');
            }

            el('flowUpdatedAt').textContent = 'Updated ' + new Date(flow.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            setLiveIndicator('live');
            showOffline(false);
        } catch (e) {
            setLiveIndicator('offline');
            showOffline(true);
        }
    }

    function destroyChart() {
        if (powerChart) {
            powerChart.destroy();
            powerChart = null;
        }
    }

    function formatChartLabel(granularity, label) {
        if (granularity === '15min') {
            return new Date(label).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        if (granularity === 'daily') {
            return new Date(label + 'T00:00:00').toLocaleDateString([], { month: 'short', day: 'numeric' });
        }
        if (granularity === 'monthly') {
            return new Date(label + '-01T00:00:00').toLocaleDateString([], { month: 'short', year: '2-digit' });
        }
        return label;
    }

    function renderChart(series) {
        const labels = series.points.map((p) => formatChartLabel(series.granularity, p.label));
        const datasets = [
            { label: 'PV Output', data: series.points.map((p) => p.pv), borderColor: '#15803d', backgroundColor: 'rgba(21,128,61,0.18)', fill: true, tension: 0.35, pointRadius: 0, borderWidth: 2, yAxisID: 'y' },
            { label: 'Load', data: series.points.map((p) => p.load), borderColor: '#d97706', backgroundColor: 'rgba(217,119,6,0.10)', fill: true, tension: 0.35, pointRadius: 0, borderWidth: 2, yAxisID: 'y' },
            { label: 'Grid Import', data: series.points.map((p) => p.grid_import), borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,0.10)', fill: true, tension: 0.35, pointRadius: 0, borderWidth: 1.5, yAxisID: 'y' },
            { label: 'Grid Export', data: series.points.map((p) => p.grid_export), borderColor: '#0284c7', backgroundColor: 'rgba(2,132,199,0.10)', fill: true, tension: 0.35, pointRadius: 0, borderWidth: 1.5, yAxisID: 'y' },
            { label: 'Battery %', data: series.battery || [], borderColor: '#10b981', backgroundColor: 'transparent', borderDash: [5, 3], fill: false, tension: 0.35, pointRadius: 0, borderWidth: 1.5, yAxisID: 'y1' },
        ];

        destroyChart();
        const ctx = document.getElementById('powerChart').getContext('2d');
        powerChart = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10, weight: 'bold' } } },
                    tooltip: {
                        callbacks: {
                            label: (ctx2) => ctx2.dataset.yAxisID === 'y1'
                                ? `${ctx2.dataset.label}: ${Number(ctx2.parsed.y).toLocaleString(undefined, { maximumFractionDigits: 0 })}%`
                                : `${ctx2.dataset.label}: ${Number(ctx2.parsed.y).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${series.unit}`,
                        },
                    },
                },
                scales: {
                    y: { position: 'left', title: { display: true, text: series.unit, font: { size: 10, weight: 'bold' } }, ticks: { font: { size: 10 } } },
                    y1: { position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false }, title: { display: true, text: 'Battery %', font: { size: 10, weight: 'bold' } }, ticks: { font: { size: 10 }, callback: (v) => v + '%' } },
                    x: { ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true } },
                },
            },
        });
    }

    function renderMetrics(data) {
        const m = data.metrics;
        el('metricProduction').textContent = fmtKwh(m.production_kwh);
        el('metricConsumption').textContent = fmtKwh(m.consumption_kwh);

        const netEl = el('metricNetEnergy');
        netEl.textContent = (m.net_energy_kwh >= 0 ? '+' : '') + fmtKwh(m.net_energy_kwh);
        netEl.className = 'text-xl font-black mt-1 ' + (m.net_energy_kwh >= 0 ? 'text-emerald-600' : 'text-rose-600');

        el('metricNetRevenue').textContent = fmtMoney(m.net_revenue);
        el('metricTariff').textContent = fmtMoney(m.tariff_rate);

        el('envCo2').textContent = data.environmental.co2_tons.toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' t';
        el('envCoal').textContent = data.environmental.coal_tons.toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' t';
        el('envTrees').textContent = Math.round(data.environmental.trees).toLocaleString();

        el('chartRangeLabel').textContent = RANGE_LABELS[data.range] || '';
    }

    async function loadSummary() {
        if (!currentPlant) return;
        el('chartSkeleton').classList.remove('hidden');
        const params = new URLSearchParams({ action: 'summary', plant_id: currentPlant.id, range: currentRange });
        if (currentRange === 'custom') {
            const start = el('customStart').value;
            const end = el('customEnd').value;
            if (start) params.set('start', start);
            if (end) params.set('end', end);
        }
        try {
            const data = await fetchJson(`${API}?${params.toString()}`);
            renderMetrics(data);
            renderChart(data.series);
            showOffline(false);
        } catch (e) {
            showOffline(true);
        } finally {
            el('chartSkeleton').classList.add('hidden');
        }
    }

    function setActiveRangeTab() {
        document.querySelectorAll('.range-tab').forEach((btn) => {
            const active = btn.dataset.range === currentRange;
            btn.className = 'range-tab px-3 py-1.5 rounded-lg transition-all ' + (active ? 'bg-white text-slate-800 shadow-2xs' : 'hover:text-slate-700');
        });
        el('customRangeRow').classList.toggle('hidden', currentRange !== 'custom');
        if (currentRange === 'custom') {
            el('customRangeRow').classList.add('flex');
        }
    }

    document.querySelectorAll('.range-tab').forEach((btn) => {
        btn.addEventListener('click', () => {
            currentRange = btn.dataset.range;
            setActiveRangeTab();
            if (currentRange !== 'custom') {
                loadSummary();
            }
        });
    });

    el('applyCustom').addEventListener('click', () => {
        if (currentRange === 'custom') loadSummary();
    });

    el('plantSelect').addEventListener('change', (e) => {
        currentPlant = plants.find((p) => String(p.id) === e.target.value) || currentPlant;
        renderPlantHeader();
        loadLive();
        loadSummary();
    });

    el('residentSearch').addEventListener('input', () => {
        const list = filteredPlants();
        populatePlantSelect(list);
        if (!list.length) {
            return;
        }
        const stillVisible = list.some((p) => currentPlant && p.id === currentPlant.id);
        if (!stillVisible) {
            currentPlant = list[0];
            renderPlantHeader();
            loadLive();
            loadSummary();
        }
        el('plantSelect').value = currentPlant.id;
    });

    window.toggleTariffModal = function (show) {
        const modal = el('tariffModal');
        el('tariffError').classList.add('hidden');
        if (show) {
            el('tariffInput').value = currentPlant ? currentPlant.tariff_rate : '';
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.querySelector('div').classList.remove('scale-95');
            }, 10);
        } else {
            modal.classList.add('opacity-0');
            modal.querySelector('div').classList.add('scale-95');
            setTimeout(() => modal.classList.add('hidden'), 200);
        }
    };

    el('setTariffBtn').addEventListener('click', () => toggleTariffModal(true));

    el('saveTariffBtn').addEventListener('click', async () => {
        const rate = parseFloat(el('tariffInput').value);
        if (isNaN(rate) || rate < 0) {
            el('tariffError').textContent = 'Enter a valid, non-negative rate.';
            el('tariffError').classList.remove('hidden');
            return;
        }
        try {
            await fetchJson(`${API}?action=set_tariff`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ plant_id: currentPlant.id, tariff_rate: rate }),
            });
            currentPlant.tariff_rate = rate;
            toggleTariffModal(false);
            loadSummary();
        } catch (e) {
            el('tariffError').textContent = e.message || 'Could not save the tariff right now.';
            el('tariffError').classList.remove('hidden');
        }
    });

    async function init() {
        setActiveRangeTab();
        try {
            const data = await fetchJson(`${API}?action=plants`);
            plants = data.plants || [];
            if (!plants.length) {
                showOffline(true);
                return;
            }
            populatePlantSelect(plants);
            currentPlant = plants[0];
            renderPlantHeader();
            await loadLive();
            await loadSummary();
            liveTimer = setInterval(loadLive, 45000);
        } catch (e) {
            setLiveIndicator('offline');
            showOffline(true);
        }
    }

    init();
})();
</script>

</body>
</html>
