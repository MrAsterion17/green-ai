<?php
// AJAX endpoint backing the Weather Data Import step: fetches monthly solar
// resource climatology for a saved assessment's coordinates and persists it
// as a JSON snapshot on the solar_assessments row.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_schema.php';
require_once __DIR__ . '/../includes/site_assessment_schema.php';
require_once __DIR__ . '/../includes/weather_import.php';
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
$source = $input['source'] ?? 'nasa_power';
$period = $input['period'] ?? 'climatology';
$year = (int)($input['year'] ?? 0);

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Save the site details before importing weather data.']);
    exit();
}

if ($source !== 'nasa_power') {
    http_response_code(422);
    echo json_encode(['error' => 'That data source is not wired up yet — use NASA POWER for now.']);
    exit();
}

$stmt = $pdo->prepare("SELECT id, latitude, longitude FROM solar_assessments WHERE id = ? AND admin_id = ?");
$stmt->execute([$id, $_SESSION['admin']['id']]);
$assessment = $stmt->fetch();

if (!$assessment) {
    http_response_code(404);
    echo json_encode(['error' => 'Assessment not found.']);
    exit();
}

try {
    if ($period === 'year' && $year >= 1990 && $year <= (int)date('Y')) {
        $table = greenai_fetch_weather_year((float)$assessment['latitude'], (float)$assessment['longitude'], $year);
    } else {
        $table = greenai_fetch_weather_climatology((float)$assessment['latitude'], (float)$assessment['longitude']);
    }

    if (!$table) {
        http_response_code(502);
        echo json_encode(['error' => 'The weather data service did not return usable data for this location.']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE solar_assessments SET weather_data = ?, status = 'weather_imported' WHERE id = ? AND admin_id = ?");
    $stmt->execute([json_encode($table), $id, $_SESSION['admin']['id']]);

    echo json_encode(['success' => true, 'weather' => $table]);
} catch (Exception $e) {
    http_response_code(502);
    echo json_encode(['error' => 'Weather data import failed. Check your connection and try again.']);
}
