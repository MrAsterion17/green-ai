<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_schema.php';
require_once __DIR__ . '/../includes/site_assessment_schema.php';
require_once __DIR__ . '/../includes/geo.php';
require_once __DIR__ . '/../includes/weather_import.php';
greenai_ensure_support_tables($pdo);
greenai_ensure_site_assessment_tables($pdo);

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php?as=admin");
    exit();
}

$adminName = $_SESSION['admin']['fullname'] ?? 'Support Agent';
$activeNav = 'site-assessments';
$id = (int)($_GET['id'] ?? 0);
$assessment = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM solar_assessments WHERE id = ? AND admin_id = ?");
    $stmt->execute([$id, $_SESSION['admin']['id']]);
    $assessment = $stmt->fetch();

    if (!$assessment) {
        header("Location: site-assessments.php");
        exit();
    }
}

$latDms = $assessment ? greenai_geo_decimal_to_dms((float)$assessment['latitude'], true) : null;
$lonDms = $assessment ? greenai_geo_decimal_to_dms((float)$assessment['longitude'], false) : null;
$utcOffsetLabel = $assessment && $assessment['utc_offset_minutes'] !== null ? greenai_geo_format_offset($assessment['utc_offset_minutes']) : '';

$weatherData = $assessment && $assessment['weather_data'] ? json_decode($assessment['weather_data'], true) : null;
$systemConfig = $assessment && $assessment['system_config'] ? json_decode($assessment['system_config'], true) : null;
$results = $assessment && $assessment['results'] ? json_decode($assessment['results'], true) : null;
$currentYear = (int)date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $assessment ? 'Edit Assessment' : 'New Assessment'; ?> - Support Console | Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
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

    <main class="p-4 sm:p-8 max-w-5xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <a href="site-assessments.php" class="text-[10px] font-black text-slate-400 hover:text-slate-600 inline-flex items-center gap-1 mb-1">
                    <i class="fa-solid fa-arrow-left"></i> Back to Assessments
                </a>
                <h2 class="text-lg font-black text-slate-900 tracking-tight"><?php echo $assessment ? 'Edit Site Assessment' : 'New Solar Site Assessment'; ?></h2>
                <p class="text-slate-500 text-xs mt-1">PVsyst-style workflow: look up a site, confirm its geography, then move on to weather, system design, and simulation.</p>
            </div>
        </div>

        <!-- Stepper -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs p-3 overflow-x-auto">
            <div class="flex items-center gap-2 min-w-max text-[10px] font-black">
                <a href="#step1" class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-100 shrink-0">
                    <span class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center">1</span> Site Lookup
                </a>
                <i class="fa-solid fa-chevron-right text-slate-300 text-[9px]"></i>
                <a href="#step2" class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-100 shrink-0">
                    <span class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center">2</span> Geographical Parameters
                </a>
                <i class="fa-solid fa-chevron-right text-slate-300 text-[9px]"></i>
                <a href="#step3" class="flex items-center gap-1.5 px-3 py-2 rounded-xl <?php echo $weatherData ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-amber-50 text-amber-700 border-amber-100'; ?> border shrink-0">
                    <span class="w-5 h-5 rounded-full <?php echo $weatherData ? 'bg-emerald-600' : 'bg-amber-500'; ?> text-white flex items-center justify-center">3</span> Weather Data
                </a>
                <i class="fa-solid fa-chevron-right text-slate-300 text-[9px]"></i>
                <a href="#step4" class="flex items-center gap-1.5 px-3 py-2 rounded-xl <?php echo $weatherData ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-slate-50 text-slate-400 border-slate-100'; ?> border shrink-0">
                    <span class="w-5 h-5 rounded-full <?php echo $weatherData ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-500'; ?> flex items-center justify-center">4</span> Monthly Data
                </a>
                <i class="fa-solid fa-chevron-right text-slate-300 text-[9px]"></i>
                <a href="#step5" class="flex items-center gap-1.5 px-3 py-2 rounded-xl <?php echo $systemConfig ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-amber-50 text-amber-700 border-amber-100'; ?> border shrink-0">
                    <span class="w-5 h-5 rounded-full <?php echo $systemConfig ? 'bg-emerald-600' : 'bg-amber-500'; ?> text-white flex items-center justify-center">5</span> Project &amp; System
                </a>
                <i class="fa-solid fa-chevron-right text-slate-300 text-[9px]"></i>
                <a href="#step6" class="flex items-center gap-1.5 px-3 py-2 rounded-xl <?php echo $results ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-slate-50 text-slate-400 border-slate-100'; ?> border shrink-0">
                    <span class="w-5 h-5 rounded-full <?php echo $results ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-500'; ?> flex items-center justify-center">6</span> Simulation Results
                </a>
            </div>
        </div>

        <!-- Toast / status banner -->
        <div id="statusBanner" class="hidden text-xs font-bold p-4 rounded-2xl border"></div>

        <!-- Step 1: Site Lookup -->
        <div id="step1" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs space-y-4">
            <div>
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-magnifying-glass-location text-[#15803d]"></i> Site Lookup
                </h3>
                <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Search a site name or address to pull its coordinates, altitude, and time zone automatically.</p>
            </div>

            <div class="relative">
                <div class="relative">
                    <i class="fa-solid fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-300 text-xs"></i>
                    <input type="text" id="siteSearchInput" placeholder="Type a site name or address, e.g. Batangas City, Philippines"
                        class="w-full pl-9 pr-9 py-3 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400">
                    <i id="searchSpinner" class="fa-solid fa-circle-notch fa-spin absolute right-3.5 top-1/2 -translate-y-1/2 text-emerald-500 text-xs hidden"></i>
                </div>
                <div id="searchResults" class="hidden absolute z-20 mt-1.5 w-full bg-white border border-slate-100 rounded-xl shadow-lg max-h-64 overflow-y-auto"></div>
            </div>
            <p id="searchError" class="hidden text-[11px] font-bold text-rose-600"><i class="fa-solid fa-triangle-exclamation mr-1"></i></p>

            <div id="mapContainer" class="w-full h-64 sm:h-80 rounded-xl border border-slate-100 bg-slate-50 flex items-center justify-center overflow-hidden">
                <p class="text-slate-300 text-[11px] font-bold" id="mapPlaceholder"><i class="fa-solid fa-map-location-dot mr-1"></i> Search for a site to preview it on the map</p>
            </div>
        </div>

        <!-- Step 2: Geographical Site Parameters -->
        <div id="step2" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs space-y-5">
            <div>
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-earth-asia text-[#15803d]"></i> Geographical Site Parameters
                </h3>
                <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Auto-filled from the lookup above &mdash; every field can be overridden manually.</p>
            </div>

            <form id="siteForm" class="space-y-5" onsubmit="return false;">
                <input type="hidden" id="assessmentId" value="<?php echo $assessment ? (int)$assessment['id'] : ''; ?>">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Site Name</label>
                        <input type="text" id="siteName" required value="<?php echo $assessment ? htmlspecialchars($assessment['site_name']) : ''; ?>"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Client Name</label>
                        <input type="text" id="clientName" value="<?php echo $assessment ? htmlspecialchars($assessment['client_name'] ?? '') : ''; ?>"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Country</label>
                        <input type="text" id="country" value="<?php echo $assessment ? htmlspecialchars($assessment['country'] ?? '') : ''; ?>"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Region / Province</label>
                        <input type="text" id="region" value="<?php echo $assessment ? htmlspecialchars($assessment['region'] ?? '') : ''; ?>"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Latitude (decimal)</label>
                        <input type="number" step="0.000001" id="latitude" value="<?php echo $assessment ? $assessment['latitude'] : ''; ?>"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Longitude (decimal)</label>
                        <input type="number" step="0.000001" id="longitude" value="<?php echo $assessment ? $assessment['longitude'] : ''; ?>"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Latitude (deg / min / sec)</label>
                        <div class="grid grid-cols-4 gap-1.5">
                            <input type="number" id="latDeg" placeholder="deg" value="<?php echo $latDms ? $latDms['deg'] : ''; ?>" class="dms-input px-2 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 w-full">
                            <input type="number" id="latMin" placeholder="min" value="<?php echo $latDms ? $latDms['min'] : ''; ?>" class="dms-input px-2 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 w-full">
                            <input type="number" step="0.1" id="latSec" placeholder="sec" value="<?php echo $latDms ? $latDms['sec'] : ''; ?>" class="dms-input px-2 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 w-full">
                            <select id="latHemisphere" class="dms-input px-2 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 w-full">
                                <option value="N" <?php echo (!$latDms || $latDms['hemisphere'] === 'N') ? 'selected' : ''; ?>>N</option>
                                <option value="S" <?php echo ($latDms && $latDms['hemisphere'] === 'S') ? 'selected' : ''; ?>>S</option>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Longitude (deg / min / sec)</label>
                        <div class="grid grid-cols-4 gap-1.5">
                            <input type="number" id="lonDeg" placeholder="deg" value="<?php echo $lonDms ? $lonDms['deg'] : ''; ?>" class="dms-input px-2 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 w-full">
                            <input type="number" id="lonMin" placeholder="min" value="<?php echo $lonDms ? $lonDms['min'] : ''; ?>" class="dms-input px-2 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 w-full">
                            <input type="number" step="0.1" id="lonSec" placeholder="sec" value="<?php echo $lonDms ? $lonDms['sec'] : ''; ?>" class="dms-input px-2 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 w-full">
                            <select id="lonHemisphere" class="dms-input px-2 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 w-full">
                                <option value="E" <?php echo (!$lonDms || $lonDms['hemisphere'] === 'E') ? 'selected' : ''; ?>>E</option>
                                <option value="W" <?php echo ($lonDms && $lonDms['hemisphere'] === 'W') ? 'selected' : ''; ?>>W</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Altitude (m)</label>
                        <input type="number" step="0.1" id="altitude" value="<?php echo $assessment && $assessment['altitude'] !== null ? $assessment['altitude'] : ''; ?>"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Time Zone</label>
                        <input type="text" id="timezoneName" value="<?php echo $assessment ? htmlspecialchars($assessment['timezone_name'] ?? '') : ''; ?>" placeholder="e.g. Asia/Manila"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">UTC Offset (minutes)</label>
                        <input type="number" id="utcOffsetMinutes" value="<?php echo $assessment && $assessment['utc_offset_minutes'] !== null ? $assessment['utc_offset_minutes'] : ''; ?>"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                        <p class="text-[9px] font-bold text-slate-400" id="utcOffsetLabel"><?php echo htmlspecialchars($utcOffsetLabel); ?></p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" id="saveDraftBtn" class="inline-flex items-center gap-2 bg-[#1b5e20] hover:bg-[#144517] text-white text-xs font-black px-5 py-2.5 rounded-xl transition-all">
                        <i class="fa-solid fa-floppy-disk"></i> <span id="saveDraftBtnLabel">Save Draft</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Step 3: Weather Data Import -->
        <div id="step3" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs space-y-4">
            <div>
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-cloud-sun text-[#15803d]"></i> Weather Data Import
                </h3>
                <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Monthly GHI, diffuse irradiation, temperature, wind, relative humidity, and an estimated Linke turbidity for this site.</p>
            </div>

            <?php if (!$assessment): ?>
            <p class="text-[11px] font-bold text-amber-600 bg-amber-50 border border-amber-100 rounded-xl p-3"><i class="fa-solid fa-lock mr-1"></i> Save the site details in Step 2 first to unlock weather import.</p>
            <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="space-y-1">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Data Source</label>
                    <select id="weatherSource" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                        <option value="nasa_power">NASA POWER (free)</option>
                        <option value="pvgis" disabled>PVGIS &mdash; coming soon</option>
                        <option value="meteonorm" disabled>Meteonorm &mdash; requires license</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Averaging Period</label>
                    <select id="weatherPeriod" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                        <option value="climatology">Long-term monthly average</option>
                        <option value="year">Specific year</option>
                    </select>
                </div>
                <div class="space-y-1" id="weatherYearWrap" style="display:none;">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Year</label>
                    <input type="number" id="weatherYear" min="1990" max="<?php echo $currentYear; ?>" value="<?php echo $currentYear - 1; ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" id="importWeatherBtn" class="inline-flex items-center gap-2 bg-[#1b5e20] hover:bg-[#144517] text-white text-xs font-black px-5 py-2.5 rounded-xl transition-all">
                    <i class="fa-solid fa-cloud-arrow-down"></i> <span id="importWeatherBtnLabel">Import Weather Data</span>
                </button>
                <span id="weatherImportedBadge" class="text-[10px] font-bold text-emerald-600 <?php echo $weatherData ? '' : 'hidden'; ?>"><i class="fa-solid fa-circle-check"></i> Imported from <span id="weatherImportedSource"><?php echo htmlspecialchars($weatherData['source'] ?? ''); ?></span></span>
            </div>
            <p id="weatherError" class="hidden text-[11px] font-bold text-rose-600"></p>
            <?php endif; ?>
        </div>

        <!-- Step 4: Monthly Data Table -->
        <div id="step4" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-table text-[#15803d]"></i> Monthly Data Table
                    </h3>
                    <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Jan&ndash;Dec climate data with an annual summary row.</p>
                </div>
                <div id="exportButtons" class="flex items-center gap-2 <?php echo $weatherData ? '' : 'hidden'; ?>">
                    <a id="exportCsvLink" href="<?php echo $assessment ? '../api/weatherexport.php?id=' . (int)$assessment['id'] : '#'; ?>" class="inline-flex items-center gap-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 text-[10px] font-black px-3 py-2 rounded-xl transition-all">
                        <i class="fa-solid fa-file-csv"></i> Export CSV
                    </a>
                    <a id="exportPdfLink" href="<?php echo $assessment ? 'site-assessment-print.php?id=' . (int)$assessment['id'] : '#'; ?>" target="_blank" class="inline-flex items-center gap-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 text-[10px] font-black px-3 py-2 rounded-xl transition-all">
                        <i class="fa-solid fa-file-pdf"></i> Export PDF
                    </a>
                </div>
            </div>
            <div id="monthlyTableContainer" class="overflow-x-auto">
                <p class="text-slate-300 text-[11px] font-bold" id="monthlyTablePlaceholder"><?php echo $weatherData ? '' : 'Import weather data above to see the monthly table.'; ?></p>
            </div>
        </div>

        <!-- Step 5: Project & System Setup -->
        <div id="step5" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs space-y-4">
            <div>
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-solar-panel text-[#15803d]"></i> Project &amp; System Setup
                </h3>
                <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Define the PV system to evaluate at this site.</p>
            </div>

            <?php if (!$assessment): ?>
            <p class="text-[11px] font-bold text-amber-600 bg-amber-50 border border-amber-100 rounded-xl p-3"><i class="fa-solid fa-lock mr-1"></i> Save the site details in Step 2 first to unlock system setup.</p>
            <?php else: ?>
            <form id="systemForm" class="space-y-4" onsubmit="return false;">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Project Name</label>
                        <input type="text" id="projectName" value="<?php echo htmlspecialchars($systemConfig['project_name'] ?? ''); ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Client Name</label>
                        <input type="text" id="systemClientName" value="<?php echo htmlspecialchars($assessment['client_name'] ?? ''); ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700" disabled>
                        <p class="text-[9px] font-bold text-slate-400">Set in Step 2 &mdash; Geographical Site Parameters.</p>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Module Type</label>
                        <input type="text" id="moduleType" placeholder="e.g. Monocrystalline PERC" value="<?php echo htmlspecialchars($systemConfig['module_type'] ?? ''); ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Module Wattage (W)</label>
                        <input type="number" step="1" id="moduleWattage" value="<?php echo $systemConfig['module_wattage_w'] ?? ''; ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Inverter Type</label>
                        <input type="text" id="inverterType" placeholder="e.g. String Inverter 50kW" value="<?php echo htmlspecialchars($systemConfig['inverter_type'] ?? ''); ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Number of Tables</label>
                        <input type="number" step="1" id="tableCount" value="<?php echo $systemConfig['table_count'] ?? ''; ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Number of Modules</label>
                        <input type="number" step="1" id="moduleCount" value="<?php echo $systemConfig['module_count'] ?? ''; ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">System Size (kWp)</label>
                        <input type="number" step="0.01" id="systemSizeKwp" value="<?php echo $systemConfig['system_size_kwp'] ?? ''; ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                        <p class="text-[9px] font-bold text-slate-400">Auto-calculated from modules &times; wattage &mdash; override any time.</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-1">
                    <button type="button" id="saveSystemBtn" class="inline-flex items-center gap-2 bg-[#1b5e20] hover:bg-[#144517] text-white text-xs font-black px-5 py-2.5 rounded-xl transition-all">
                        <i class="fa-solid fa-floppy-disk"></i> <span id="saveSystemBtnLabel">Save System Config</span>
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>

        <!-- Step 6: Run Simulation -->
        <div id="step6" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs space-y-4">
            <div>
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-chart-line text-[#15803d]"></i> Run Simulation
                </h3>
                <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Simplified specific-yield estimate from the imported irradiation and system size above &mdash; not a full PVsyst loss-diagram simulation.</p>
            </div>

            <?php if (!$assessment): ?>
            <p class="text-[11px] font-bold text-amber-600 bg-amber-50 border border-amber-100 rounded-xl p-3"><i class="fa-solid fa-lock mr-1"></i> Complete Steps 3 and 5 first to unlock simulation.</p>
            <?php else: ?>
            <div class="flex flex-wrap items-end gap-3">
                <div class="space-y-1">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Assumed Performance Ratio</label>
                    <input type="number" step="0.01" min="0.01" max="1" id="performanceRatio" value="<?php echo $results['performance_ratio'] ?? '0.80'; ?>" class="w-32 px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                </div>
                <button type="button" id="runSimulationBtn" class="inline-flex items-center gap-2 bg-[#1b5e20] hover:bg-[#144517] text-white text-xs font-black px-5 py-2.5 rounded-xl transition-all">
                    <i class="fa-solid fa-play"></i> <span id="runSimulationBtnLabel">Run Simulation</span>
                </button>
            </div>
            <p id="simulationError" class="hidden text-[11px] font-bold text-rose-600"></p>

            <div id="resultsCards" class="grid grid-cols-1 sm:grid-cols-3 gap-4 <?php echo $results ? '' : 'hidden'; ?>">
                <div class="bg-slate-50 border border-slate-100 p-4 rounded-2xl">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Annual Energy Production</p>
                    <h4 class="text-xl font-black text-slate-900 mt-1"><span id="resultAnnualEnergy"><?php echo $results ? number_format($results['annual_energy_kwh']) : '--'; ?></span> <span class="text-xs font-bold text-slate-400">kWh/yr</span></h4>
                </div>
                <div class="bg-slate-50 border border-slate-100 p-4 rounded-2xl">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Specific Yield</p>
                    <h4 class="text-xl font-black text-slate-900 mt-1"><span id="resultSpecificYield"><?php echo $results ? number_format($results['specific_yield_kwh_per_kwp'], 1) : '--'; ?></span> <span class="text-xs font-bold text-slate-400">kWh/kWp/yr</span></h4>
                </div>
                <div class="bg-slate-50 border border-slate-100 p-4 rounded-2xl">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Performance Ratio</p>
                    <h4 class="text-xl font-black text-emerald-600 mt-1"><span id="resultPerformanceRatio"><?php echo $results ? number_format($results['performance_ratio'] * 100, 1) : '--'; ?></span><span class="text-xs font-bold text-slate-400">%</span></h4>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script type="application/json" id="bootstrapWeatherData"><?php echo $weatherData ? json_encode($weatherData) : 'null'; ?></script>

    <script>
    (function () {
        const searchInput = document.getElementById('siteSearchInput');
        const searchSpinner = document.getElementById('searchSpinner');
        const searchResults = document.getElementById('searchResults');
        const searchError = document.getElementById('searchError');
        const mapContainer = document.getElementById('mapContainer');
        const mapPlaceholder = document.getElementById('mapPlaceholder');
        const statusBanner = document.getElementById('statusBanner');

        const fields = {
            siteName: document.getElementById('siteName'),
            clientName: document.getElementById('clientName'),
            country: document.getElementById('country'),
            region: document.getElementById('region'),
            latitude: document.getElementById('latitude'),
            longitude: document.getElementById('longitude'),
            altitude: document.getElementById('altitude'),
            timezoneName: document.getElementById('timezoneName'),
            utcOffsetMinutes: document.getElementById('utcOffsetMinutes'),
            utcOffsetLabel: document.getElementById('utcOffsetLabel'),
            latDeg: document.getElementById('latDeg'),
            latMin: document.getElementById('latMin'),
            latSec: document.getElementById('latSec'),
            latHemisphere: document.getElementById('latHemisphere'),
            lonDeg: document.getElementById('lonDeg'),
            lonMin: document.getElementById('lonMin'),
            lonSec: document.getElementById('lonSec'),
            lonHemisphere: document.getElementById('lonHemisphere'),
        };

        let map = null;
        let marker = null;
        let searchAbortController = null;
        let searchDebounceTimer = null;
        let syncingDms = false;

        function showBanner(message, tone) {
            statusBanner.textContent = message;
            statusBanner.className = 'text-xs font-bold p-4 rounded-2xl border ' + (
                tone === 'error' ? 'bg-rose-50 text-rose-700 border-rose-100' :
                tone === 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' :
                'bg-amber-50 text-amber-700 border-amber-100'
            );
            statusBanner.classList.remove('hidden');
        }

        function hideBanner() {
            statusBanner.classList.add('hidden');
        }

        function formatOffset(minutes) {
            if (minutes === null || minutes === undefined || minutes === '') return '';
            const sign = minutes < 0 ? '-' : '+';
            const abs = Math.abs(minutes);
            const h = Math.floor(abs / 60);
            const m = abs % 60;
            return 'UTC' + sign + String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }

        function decimalToDms(decimal, isLat) {
            const hemisphere = isLat ? (decimal >= 0 ? 'N' : 'S') : (decimal >= 0 ? 'E' : 'W');
            const abs = Math.abs(decimal);
            const deg = Math.floor(abs);
            const minFloat = (abs - deg) * 60;
            const min = Math.floor(minFloat);
            const sec = Math.round((minFloat - min) * 60 * 10) / 10;
            return { deg, min, sec, hemisphere };
        }

        function dmsToDecimal(deg, min, sec, hemisphere) {
            let value = Math.abs(deg || 0) + Math.abs(min || 0) / 60 + Math.abs(sec || 0) / 3600;
            if (hemisphere === 'S' || hemisphere === 'W') value = -value;
            return value;
        }

        function updateDmsFromDecimal(lat, lon) {
            syncingDms = true;
            if (!isNaN(lat)) {
                const d = decimalToDms(lat, true);
                fields.latDeg.value = d.deg;
                fields.latMin.value = d.min;
                fields.latSec.value = d.sec;
                fields.latHemisphere.value = d.hemisphere;
            }
            if (!isNaN(lon)) {
                const d = decimalToDms(lon, false);
                fields.lonDeg.value = d.deg;
                fields.lonMin.value = d.min;
                fields.lonSec.value = d.sec;
                fields.lonHemisphere.value = d.hemisphere;
            }
            syncingDms = false;
        }

        function ensureMap(lat, lon) {
            mapPlaceholder.classList.add('hidden');
            if (!map) {
                mapContainer.innerHTML = '';
                map = L.map(mapContainer).setView([lat, lon], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19,
                }).addTo(map);
                marker = L.marker([lat, lon]).addTo(map);
            } else {
                map.setView([lat, lon], 13);
                marker.setLatLng([lat, lon]);
            }
            setTimeout(function () { map.invalidateSize(); }, 150);
        }

        function applyResult(result) {
            fields.siteName.value = result.name || fields.siteName.value;
            fields.country.value = result.country || '';
            fields.region.value = result.region || '';
            fields.latitude.value = result.latitude;
            fields.longitude.value = result.longitude;
            fields.altitude.value = result.altitude !== null ? result.altitude : '';
            fields.timezoneName.value = result.timezone || '';
            fields.utcOffsetMinutes.value = result.utc_offset_minutes !== null ? result.utc_offset_minutes : '';
            fields.utcOffsetLabel.textContent = formatOffset(result.utc_offset_minutes);
            updateDmsFromDecimal(parseFloat(result.latitude), parseFloat(result.longitude));
            ensureMap(result.latitude, result.longitude);
            searchResults.classList.add('hidden');
            searchInput.value = [result.name, result.region, result.country].filter(Boolean).join(', ');
        }

        searchInput.addEventListener('input', function () {
            const q = searchInput.value.trim();
            searchError.classList.add('hidden');
            clearTimeout(searchDebounceTimer);

            if (q.length < 2) {
                searchResults.classList.add('hidden');
                return;
            }

            searchDebounceTimer = setTimeout(function () {
                if (searchAbortController) searchAbortController.abort();
                searchAbortController = new AbortController();
                searchSpinner.classList.remove('hidden');

                fetch('../api/geosearch.php?q=' + encodeURIComponent(q), { signal: searchAbortController.signal })
                    .then(function (res) {
                        if (!res.ok) throw new Error('lookup_failed');
                        return res.json();
                    })
                    .then(function (data) {
                        searchSpinner.classList.add('hidden');
                        if (data.error) throw new Error(data.error);

                        const results = data.results || [];
                        if (results.length === 0) {
                            searchResults.innerHTML = '<div class="p-3 text-[11px] font-bold text-slate-400">No matches found. Try a different name or add a country.</div>';
                            searchResults.classList.remove('hidden');
                            return;
                        }

                        searchResults.innerHTML = results.map(function (r, i) {
                            const subtitle = [r.region, r.country].filter(Boolean).join(', ');
                            return '<button type="button" data-idx="' + i + '" class="result-item w-full text-left px-4 py-2.5 hover:bg-slate-50 border-b border-slate-50 last:border-0">' +
                                '<span class="block text-xs font-bold text-slate-800">' + r.name + '</span>' +
                                '<span class="block text-[10px] text-slate-400">' + subtitle + ' &middot; ' + r.latitude.toFixed(4) + ', ' + r.longitude.toFixed(4) + '</span>' +
                                '</button>';
                        }).join('');
                        searchResults.classList.remove('hidden');

                        searchResults.querySelectorAll('.result-item').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                applyResult(results[parseInt(btn.dataset.idx, 10)]);
                            });
                        });
                    })
                    .catch(function (err) {
                        searchSpinner.classList.add('hidden');
                        if (err.name === 'AbortError') return;
                        searchResults.classList.add('hidden');
                        searchError.textContent = 'Site lookup failed. Check your connection and try again.';
                        searchError.classList.remove('hidden');
                    });
            }, 350);
        });

        document.addEventListener('click', function (e) {
            if (!searchResults.contains(e.target) && e.target !== searchInput) {
                searchResults.classList.add('hidden');
            }
        });

        // Manual decimal edits -> keep DMS + map marker in sync.
        [fields.latitude, fields.longitude].forEach(function (input) {
            input.addEventListener('change', function () {
                const lat = parseFloat(fields.latitude.value);
                const lon = parseFloat(fields.longitude.value);
                if (!isNaN(lat) && !isNaN(lon)) {
                    updateDmsFromDecimal(lat, lon);
                    ensureMap(lat, lon);
                }
            });
        });

        // Manual DMS edits -> keep decimal + map marker in sync.
        ['latDeg', 'latMin', 'latSec', 'latHemisphere', 'lonDeg', 'lonMin', 'lonSec', 'lonHemisphere'].forEach(function (key) {
            fields[key].addEventListener('change', function () {
                if (syncingDms) return;
                const lat = dmsToDecimal(parseFloat(fields.latDeg.value), parseFloat(fields.latMin.value), parseFloat(fields.latSec.value), fields.latHemisphere.value);
                const lon = dmsToDecimal(parseFloat(fields.lonDeg.value), parseFloat(fields.lonMin.value), parseFloat(fields.lonSec.value), fields.lonHemisphere.value);
                fields.latitude.value = lat.toFixed(6);
                fields.longitude.value = lon.toFixed(6);
                ensureMap(lat, lon);
            });
        });

        fields.utcOffsetMinutes.addEventListener('input', function () {
            fields.utcOffsetLabel.textContent = formatOffset(parseInt(fields.utcOffsetMinutes.value, 10));
        });

        // Restore map on load if this assessment already has coordinates.
        <?php if ($assessment): ?>
        ensureMap(<?php echo (float)$assessment['latitude']; ?>, <?php echo (float)$assessment['longitude']; ?>);
        <?php endif; ?>

        document.getElementById('saveDraftBtn').addEventListener('click', function () {
            const btn = this;
            const label = document.getElementById('saveDraftBtnLabel');
            hideBanner();

            if (!fields.siteName.value.trim() || fields.latitude.value === '' || fields.longitude.value === '') {
                showBanner('Site name, latitude, and longitude are required before saving.', 'error');
                return;
            }

            const idField = document.getElementById('assessmentId');
            const isNewRecord = !idField.value;

            const payload = {
                id: idField.value || null,
                site_name: fields.siteName.value.trim(),
                client_name: fields.clientName.value.trim(),
                country: fields.country.value.trim(),
                region: fields.region.value.trim(),
                latitude: parseFloat(fields.latitude.value),
                longitude: parseFloat(fields.longitude.value),
                altitude: fields.altitude.value !== '' ? parseFloat(fields.altitude.value) : null,
                timezone_name: fields.timezoneName.value.trim(),
                utc_offset_minutes: fields.utcOffsetMinutes.value !== '' ? parseInt(fields.utcOffsetMinutes.value, 10) : null,
            };

            btn.disabled = true;
            label.textContent = 'Saving...';

            fetch('../api/siteassessmentsave.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    if (!result.ok || result.data.error) {
                        btn.disabled = false;
                        label.textContent = 'Save Draft';
                        showBanner(result.data.error || 'Could not save this assessment.', 'error');
                        return;
                    }
                    if (isNewRecord) {
                        // Full navigation so Steps 3, 5, and 6 (gated server-side on having an id) unlock.
                        window.location.href = 'site-assessment.php?id=' + result.data.id;
                        return;
                    }
                    idField.value = result.data.id;
                    window.history.replaceState({}, '', 'site-assessment.php?id=' + result.data.id);
                    btn.disabled = false;
                    label.textContent = 'Save Draft';
                    showBanner('Site details saved.', 'success');
                })
                .catch(function () {
                    btn.disabled = false;
                    label.textContent = 'Save Draft';
                    showBanner('Network error &mdash; could not reach the server.', 'error');
                });
        });

        // ---- Step 3: Weather Data Import ----------------------------------
        const weatherSourceEl = document.getElementById('weatherSource');
        const weatherPeriodEl = document.getElementById('weatherPeriod');
        const weatherYearWrap = document.getElementById('weatherYearWrap');
        const weatherYearEl = document.getElementById('weatherYear');
        const importWeatherBtn = document.getElementById('importWeatherBtn');
        const weatherError = document.getElementById('weatherError');
        const weatherImportedBadge = document.getElementById('weatherImportedBadge');
        const weatherImportedSource = document.getElementById('weatherImportedSource');
        const monthlyTableContainer = document.getElementById('monthlyTableContainer');
        const exportButtons = document.getElementById('exportButtons');

        if (weatherPeriodEl) {
            weatherPeriodEl.addEventListener('change', function () {
                weatherYearWrap.style.display = weatherPeriodEl.value === 'year' ? '' : 'none';
            });
        }

        function renderWeatherTable(data) {
            if (!data || !data.months) return;

            const rows = data.months.map(function (m) {
                return '<tr class="border-b border-slate-50">' +
                    '<td class="p-2.5 font-bold text-slate-800">' + m.month + '</td>' +
                    '<td class="p-2.5 text-right">' + m.ghi_kwh_m2_month + '</td>' +
                    '<td class="p-2.5 text-right">' + m.dhi_kwh_m2_month + '</td>' +
                    '<td class="p-2.5 text-right">' + m.avg_temp_c + '</td>' +
                    '<td class="p-2.5 text-right">' + m.wind_speed_ms + '</td>' +
                    '<td class="p-2.5 text-right">' + m.relative_humidity_pct + '</td>' +
                    '<td class="p-2.5 text-right">' + m.linke_turbidity_est + '</td>' +
                    '</tr>';
            }).join('');

            const a = data.annual;
            const variabilityText = data.variability_pct !== null && data.variability_pct !== undefined ? data.variability_pct + '%' : 'n/a';

            monthlyTableContainer.innerHTML =
                '<table class="w-full text-left border-collapse text-[11px]">' +
                '<thead><tr class="bg-slate-50 border-b border-slate-100 text-[9px] font-black text-slate-400 uppercase tracking-wider">' +
                '<th class="p-2.5">Month</th><th class="p-2.5 text-right">GHI (kWh/m&sup2;)</th><th class="p-2.5 text-right">DHI (kWh/m&sup2;)</th>' +
                '<th class="p-2.5 text-right">Temp (&deg;C)</th><th class="p-2.5 text-right">Wind (m/s)</th><th class="p-2.5 text-right">RH (%)</th><th class="p-2.5 text-right">Linke TL*</th>' +
                '</tr></thead><tbody class="text-slate-700 font-semibold">' + rows +
                '<tr class="bg-slate-50 font-black text-slate-900">' +
                '<td class="p-2.5">Annual</td><td class="p-2.5 text-right">' + a.ghi_kwh_m2_year + '</td><td class="p-2.5 text-right">' + a.dhi_kwh_m2_year + '</td>' +
                '<td class="p-2.5 text-right">' + a.avg_temp_c + '</td><td class="p-2.5 text-right">' + a.avg_wind_speed_ms + '</td><td class="p-2.5 text-right">' + a.avg_relative_humidity_pct + '</td><td class="p-2.5 text-right">' + a.linke_turbidity_est + '</td>' +
                '</tr></tbody></table>' +
                '<p class="text-[9px] font-bold text-slate-400 mt-2">Source: ' + data.source + ' &middot; ' + data.period + ' &middot; Year-to-year GHI variability: ' + variabilityText +
                ' (computed from 15 years of historical data). *Linke Turbidity has no free live data source and is estimated from humidity &mdash; treat as indicative only.</p>';

            exportButtons.classList.remove('hidden');
        }

        const bootstrapWeatherEl = document.getElementById('bootstrapWeatherData');
        if (bootstrapWeatherEl) {
            try {
                const bootstrapped = JSON.parse(bootstrapWeatherEl.textContent);
                if (bootstrapped) renderWeatherTable(bootstrapped);
            } catch (e) { /* no bootstrapped weather data */ }
        }

        if (importWeatherBtn) {
            importWeatherBtn.addEventListener('click', function () {
                const btn = this;
                const label = document.getElementById('importWeatherBtnLabel');
                weatherError.classList.add('hidden');
                btn.disabled = true;
                label.textContent = 'Importing...';

                fetch('../api/weatherimport.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: document.getElementById('assessmentId').value,
                        source: weatherSourceEl.value,
                        period: weatherPeriodEl.value,
                        year: weatherYearEl.value,
                    }),
                })
                    .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                    .then(function (result) {
                        btn.disabled = false;
                        label.textContent = 'Import Weather Data';
                        if (!result.ok || result.data.error) {
                            weatherError.textContent = result.data.error || 'Weather import failed.';
                            weatherError.classList.remove('hidden');
                            return;
                        }
                        renderWeatherTable(result.data.weather);
                        weatherImportedSource.textContent = result.data.weather.source;
                        weatherImportedBadge.classList.remove('hidden');
                    })
                    .catch(function () {
                        btn.disabled = false;
                        label.textContent = 'Import Weather Data';
                        weatherError.textContent = 'Network error — could not reach the server.';
                        weatherError.classList.remove('hidden');
                    });
            });
        }

        // ---- Step 5: Project & System Setup --------------------------------
        const moduleCountEl = document.getElementById('moduleCount');
        const moduleWattageEl = document.getElementById('moduleWattage');
        const systemSizeKwpEl = document.getElementById('systemSizeKwp');
        let systemSizeManuallyEdited = !!(systemSizeKwpEl && systemSizeKwpEl.value);

        function autoCalcSystemSize() {
            if (systemSizeManuallyEdited) return;
            const count = parseFloat(moduleCountEl.value);
            const watt = parseFloat(moduleWattageEl.value);
            if (!isNaN(count) && !isNaN(watt) && count > 0 && watt > 0) {
                systemSizeKwpEl.value = ((count * watt) / 1000).toFixed(2);
            }
        }

        if (moduleCountEl) {
            moduleCountEl.addEventListener('input', autoCalcSystemSize);
            moduleWattageEl.addEventListener('input', autoCalcSystemSize);
            systemSizeKwpEl.addEventListener('input', function () { systemSizeManuallyEdited = true; });

            document.getElementById('saveSystemBtn').addEventListener('click', function () {
                const btn = this;
                const label = document.getElementById('saveSystemBtnLabel');
                hideBanner();

                const projectName = document.getElementById('projectName').value.trim();
                const sizeKwp = systemSizeKwpEl.value;

                if (!projectName || !sizeKwp || parseFloat(sizeKwp) <= 0) {
                    showBanner('Project name and a system size (kWp) greater than zero are required.', 'error');
                    return;
                }

                btn.disabled = true;
                label.textContent = 'Saving...';

                fetch('../api/systemconfigsave.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: document.getElementById('assessmentId').value,
                        project_name: projectName,
                        module_type: document.getElementById('moduleType').value.trim(),
                        module_wattage: moduleWattageEl.value,
                        inverter_type: document.getElementById('inverterType').value.trim(),
                        table_count: document.getElementById('tableCount').value,
                        module_count: moduleCountEl.value,
                        system_size_kwp: sizeKwp,
                    }),
                })
                    .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                    .then(function (result) {
                        btn.disabled = false;
                        label.textContent = 'Save System Config';
                        if (!result.ok || result.data.error) {
                            showBanner(result.data.error || 'Could not save the system configuration.', 'error');
                            return;
                        }
                        showBanner('System configuration saved. You can now run the simulation.', 'success');
                    })
                    .catch(function () {
                        btn.disabled = false;
                        label.textContent = 'Save System Config';
                        showBanner('Network error &mdash; could not reach the server.', 'error');
                    });
            });
        }

        // ---- Step 6: Run Simulation -----------------------------------------
        const runSimulationBtn = document.getElementById('runSimulationBtn');
        if (runSimulationBtn) {
            runSimulationBtn.addEventListener('click', function () {
                const btn = this;
                const label = document.getElementById('runSimulationBtnLabel');
                const simulationError = document.getElementById('simulationError');
                simulationError.classList.add('hidden');
                btn.disabled = true;
                label.textContent = 'Running...';

                fetch('../api/runsimulation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: document.getElementById('assessmentId').value,
                        performance_ratio: document.getElementById('performanceRatio').value,
                    }),
                })
                    .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                    .then(function (result) {
                        btn.disabled = false;
                        label.textContent = 'Run Simulation';
                        if (!result.ok || result.data.error) {
                            simulationError.textContent = result.data.error || 'Simulation failed.';
                            simulationError.classList.remove('hidden');
                            return;
                        }
                        const r = result.data.results;
                        document.getElementById('resultAnnualEnergy').textContent = Math.round(r.annual_energy_kwh).toLocaleString();
                        document.getElementById('resultSpecificYield').textContent = r.specific_yield_kwh_per_kwp.toFixed(1);
                        document.getElementById('resultPerformanceRatio').textContent = (r.performance_ratio * 100).toFixed(1);
                        document.getElementById('resultsCards').classList.remove('hidden');
                    })
                    .catch(function () {
                        btn.disabled = false;
                        label.textContent = 'Run Simulation';
                        simulationError.textContent = 'Network error — could not reach the server.';
                        simulationError.classList.remove('hidden');
                    });
            });
        }
    })();
    </script>
</body>
</html>
