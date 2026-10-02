<?php
// AJAX endpoint backing Run Simulation: a simplified specific-yield estimate
// (annual GHI x performance ratio x system size). This is NOT a full PVsyst
// loss-diagram simulation (no shading/soiling/wiring/inverter-curve
// modeling) — it's a transparent first-pass estimate clearly labeled as such
// in the UI, useful for pre-feasibility screening.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_schema.php';
require_once __DIR__ . '/../includes/site_assessment_schema.php';
greenai_ensure_support_tables($pdo);
greenai_ensure_site_assessment_tables($pdo);

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int)($input['id'] ?? 0);
$performanceRatio = isset($input['performance_ratio']) && $input['performance_ratio'] !== '' ? (float)$input['performance_ratio'] : 0.80;

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Save the site details first.']);
    exit();
}

if ($performanceRatio <= 0 || $performanceRatio > 1) {
    http_response_code(422);
    echo json_encode(['error' => 'Performance ratio must be between 0 and 1 (e.g. 0.80 for 80%).']);
    exit();
}

$stmt = $pdo->prepare("SELECT weather_data, system_config FROM solar_assessments WHERE id = ? AND admin_id = ?");
$stmt->execute([$id, $_SESSION['admin']['id']]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Assessment not found.']);
    exit();
}

$weather = $row['weather_data'] ? json_decode($row['weather_data'], true) : null;
$system = $row['system_config'] ? json_decode($row['system_config'], true) : null;

if (!$weather || !isset($weather['annual']['ghi_kwh_m2_year'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Import weather data before running a simulation.']);
    exit();
}

if (!$system || empty($system['system_size_kwp'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Complete the Project & System Setup step before running a simulation.']);
    exit();
}

$annualGhi = (float)$weather['annual']['ghi_kwh_m2_year'];
$systemSizeKwp = (float)$system['system_size_kwp'];

$specificYield = round($annualGhi * $performanceRatio, 1);
$annualEnergyKwh = round($specificYield * $systemSizeKwp, 0);

$results = [
    'annual_energy_kwh'        => $annualEnergyKwh,
    'specific_yield_kwh_per_kwp' => $specificYield,
    'performance_ratio'        => $performanceRatio,
    'annual_ghi_kwh_m2'        => $annualGhi,
    'system_size_kwp'          => $systemSizeKwp,
    'computed_at'              => date('c'),
    'note'                     => 'Simplified specific-yield estimate (annual GHI x performance ratio x system size) — not a full PVsyst loss-diagram simulation.',
];

try {
    $stmt = $pdo->prepare("UPDATE solar_assessments SET results = ?, status = 'completed' WHERE id = ? AND admin_id = ?");
    $stmt->execute([json_encode($results), $id, $_SESSION['admin']['id']]);

    echo json_encode(['success' => true, 'results' => $results]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save simulation results right now.']);
}
