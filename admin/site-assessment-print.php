<?php
// Print-optimized view of a site assessment — the "Export PDF" affordance.
// No PDF library is vendored in this project, so this renders a clean,
// print-ready page and lets the browser's native "Save as PDF" produce the
// file, rather than pulling in a new dependency for one button.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_schema.php';
require_once __DIR__ . '/../includes/site_assessment_schema.php';
greenai_ensure_support_tables($pdo);
greenai_ensure_site_assessment_tables($pdo);

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php?as=admin");
    exit();
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM solar_assessments WHERE id = ? AND admin_id = ?");
$stmt->execute([$id, $_SESSION['admin']['id']]);
$a = $stmt->fetch();

if (!$a) {
    header("Location: site-assessments.php");
    exit();
}

$weather = $a['weather_data'] ? json_decode($a['weather_data'], true) : null;
$system = $a['system_config'] ? json_decode($a['system_config'], true) : null;
$results = $a['results'] ? json_decode($a['results'], true) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Report - <?php echo htmlspecialchars($a['site_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-white font-sans text-slate-800 antialiased p-8 max-w-3xl mx-auto">

    <div class="no-print mb-6 flex justify-between items-center">
        <a href="site-assessment.php?id=<?php echo (int)$a['id']; ?>" class="text-xs font-black text-slate-400 hover:text-slate-600">&larr; Back to Assessment</a>
        <button onclick="window.print()" class="bg-[#1b5e20] hover:bg-[#144517] text-white text-xs font-black px-4 py-2 rounded-xl">Print / Save as PDF</button>
    </div>

    <h1 class="text-2xl font-black text-slate-900">Solar Site Assessment Report</h1>
    <p class="text-slate-500 text-sm mt-1"><?php echo htmlspecialchars($a['site_name']); ?><?php echo $a['client_name'] ? ' &mdash; Client: ' . htmlspecialchars($a['client_name']) : ''; ?></p>

    <div class="grid grid-cols-2 gap-4 mt-6 text-xs">
        <div><span class="font-bold text-slate-400">Country / Region</span><br><?php echo htmlspecialchars(trim(($a['region'] ?? '') . ', ' . ($a['country'] ?? ''), ', ')) ?: '&mdash;'; ?></div>
        <div><span class="font-bold text-slate-400">Coordinates</span><br><?php echo number_format((float)$a['latitude'], 6); ?>, <?php echo number_format((float)$a['longitude'], 6); ?></div>
        <div><span class="font-bold text-slate-400">Altitude</span><br><?php echo $a['altitude'] !== null ? number_format((float)$a['altitude'], 1) . ' m' : '&mdash;'; ?></div>
        <div><span class="font-bold text-slate-400">Time Zone</span><br><?php echo htmlspecialchars($a['timezone_name'] ?? '&mdash;'); ?></div>
    </div>

    <?php if ($weather): ?>
    <h2 class="text-sm font-black text-slate-900 mt-8 mb-2">Monthly Weather Data (<?php echo htmlspecialchars($weather['source']); ?>, <?php echo htmlspecialchars($weather['period']); ?>)</h2>
    <table class="w-full text-[10px] border-collapse">
        <thead>
            <tr class="border-b border-slate-300 text-left">
                <th class="py-1.5">Month</th>
                <th class="py-1.5 text-right">GHI (kWh/m&sup2;)</th>
                <th class="py-1.5 text-right">DHI (kWh/m&sup2;)</th>
                <th class="py-1.5 text-right">Temp (&deg;C)</th>
                <th class="py-1.5 text-right">Wind (m/s)</th>
                <th class="py-1.5 text-right">RH (%)</th>
                <th class="py-1.5 text-right">Linke TL*</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($weather['months'] as $m): ?>
            <tr class="border-b border-slate-100">
                <td class="py-1"><?php echo htmlspecialchars($m['month']); ?></td>
                <td class="py-1 text-right"><?php echo $m['ghi_kwh_m2_month']; ?></td>
                <td class="py-1 text-right"><?php echo $m['dhi_kwh_m2_month']; ?></td>
                <td class="py-1 text-right"><?php echo $m['avg_temp_c']; ?></td>
                <td class="py-1 text-right"><?php echo $m['wind_speed_ms']; ?></td>
                <td class="py-1 text-right"><?php echo $m['relative_humidity_pct']; ?></td>
                <td class="py-1 text-right"><?php echo $m['linke_turbidity_est']; ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="font-black border-t-2 border-slate-400">
                <td class="py-1.5">Annual</td>
                <td class="py-1.5 text-right"><?php echo $weather['annual']['ghi_kwh_m2_year']; ?></td>
                <td class="py-1.5 text-right"><?php echo $weather['annual']['dhi_kwh_m2_year']; ?></td>
                <td class="py-1.5 text-right"><?php echo $weather['annual']['avg_temp_c']; ?></td>
                <td class="py-1.5 text-right"><?php echo $weather['annual']['avg_wind_speed_ms']; ?></td>
                <td class="py-1.5 text-right"><?php echo $weather['annual']['avg_relative_humidity_pct']; ?></td>
                <td class="py-1.5 text-right"><?php echo $weather['annual']['linke_turbidity_est']; ?></td>
            </tr>
        </tbody>
    </table>
    <p class="text-[9px] text-slate-400 mt-1">Year-to-year GHI variability: <?php echo $weather['variability_pct'] !== null ? $weather['variability_pct'] . '%' : 'n/a'; ?> (computed from 15 years of NASA POWER annual data). *Linke Turbidity has no free live data source and is estimated from humidity — replace with licensed Meteonorm/CAMS data for bankable reports.</p>
    <?php endif; ?>

    <?php if ($system): ?>
    <h2 class="text-sm font-black text-slate-900 mt-8 mb-2">Project &amp; System</h2>
    <div class="grid grid-cols-2 gap-4 text-xs">
        <div><span class="font-bold text-slate-400">Project Name</span><br><?php echo htmlspecialchars($system['project_name']); ?></div>
        <div><span class="font-bold text-slate-400">System Size</span><br><?php echo number_format((float)$system['system_size_kwp'], 2); ?> kWp</div>
        <div><span class="font-bold text-slate-400">Module</span><br><?php echo htmlspecialchars($system['module_type'] ?: '&mdash;'); ?><?php echo $system['module_wattage_w'] ? ' (' . $system['module_wattage_w'] . ' W)' : ''; ?></div>
        <div><span class="font-bold text-slate-400">Inverter</span><br><?php echo htmlspecialchars($system['inverter_type'] ?: '&mdash;'); ?></div>
        <div><span class="font-bold text-slate-400">Modules / Tables</span><br><?php echo $system['module_count'] ?? '&mdash;'; ?> modules / <?php echo $system['table_count'] ?? '&mdash;'; ?> tables</div>
    </div>
    <?php endif; ?>

    <?php if ($results): ?>
    <h2 class="text-sm font-black text-slate-900 mt-8 mb-2">Simulation Results</h2>
    <div class="grid grid-cols-3 gap-4 text-xs">
        <div><span class="font-bold text-slate-400">Annual Energy</span><br><?php echo number_format((float)$results['annual_energy_kwh']); ?> kWh/yr</div>
        <div><span class="font-bold text-slate-400">Specific Yield</span><br><?php echo number_format((float)$results['specific_yield_kwh_per_kwp'], 1); ?> kWh/kWp/yr</div>
        <div><span class="font-bold text-slate-400">Performance Ratio</span><br><?php echo number_format((float)$results['performance_ratio'] * 100, 1); ?>%</div>
    </div>
    <p class="text-[9px] text-slate-400 mt-2"><?php echo htmlspecialchars($results['note']); ?></p>
    <?php endif; ?>

    <p class="text-[9px] text-slate-300 mt-10">Generated by Green-AI Solar Site Assessment on <?php echo date('F j, Y g:i A'); ?>.</p>
</body>
</html>
