<?php
// Streams the saved Monthly Data Table as a CSV download.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_schema.php';
require_once __DIR__ . '/../includes/site_assessment_schema.php';
greenai_ensure_support_tables($pdo);
greenai_ensure_site_assessment_tables($pdo);

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT site_name, weather_data FROM solar_assessments WHERE id = ? AND admin_id = ?");
$stmt->execute([$id, $_SESSION['admin']['id']]);
$row = $stmt->fetch();

if (!$row || !$row['weather_data']) {
    http_response_code(404);
    exit('No weather data to export for this assessment.');
}

$weather = json_decode($row['weather_data'], true);
$filename = 'weather-' . preg_replace('/[^a-z0-9\-]+/i', '-', $row['site_name']) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Month', 'GHI (kWh/m2/day)', 'GHI (kWh/m2/month)', 'DHI (kWh/m2/day)', 'DHI (kWh/m2/month)', 'Avg Temp (C)', 'Wind Speed (m/s)', 'Relative Humidity (%)', 'Linke Turbidity (estimated)']);

foreach ($weather['months'] as $m) {
    fputcsv($out, [
        $m['month'], $m['ghi_kwh_m2_day'], $m['ghi_kwh_m2_month'], $m['dhi_kwh_m2_day'], $m['dhi_kwh_m2_month'],
        $m['avg_temp_c'], $m['wind_speed_ms'], $m['relative_humidity_pct'], $m['linke_turbidity_est'],
    ]);
}

$a = $weather['annual'];
fputcsv($out, ['ANNUAL', $a['ghi_kwh_m2_year'] / 365.25, $a['ghi_kwh_m2_year'], $a['dhi_kwh_m2_year'] / 365.25, $a['dhi_kwh_m2_year'], $a['avg_temp_c'], $a['avg_wind_speed_ms'], $a['avg_relative_humidity_pct'], $a['linke_turbidity_est']]);
fputcsv($out, []);
fputcsv($out, ['Data source', $weather['source'] ?? '']);
fputcsv($out, ['Period', $weather['period'] ?? '']);
fputcsv($out, ['Year-to-year GHI variability (%)', $weather['variability_pct'] ?? 'n/a']);

fclose($out);
