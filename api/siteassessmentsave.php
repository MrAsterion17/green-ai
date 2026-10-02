<?php
// AJAX endpoint backing the Geographical Site Parameters step: saves (insert
// or update) the site info half of a solar_assessments "project" record.
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
$siteName = trim($input['site_name'] ?? '');
$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;

if ($siteName === '' || $latitude === null || $longitude === null || !is_numeric($latitude) || !is_numeric($longitude)) {
    http_response_code(422);
    echo json_encode(['error' => 'Site name, latitude, and longitude are required.']);
    exit();
}

$clientName = trim($input['client_name'] ?? '') ?: null;
$country = trim($input['country'] ?? '') ?: null;
$region = trim($input['region'] ?? '') ?: null;
$altitude = isset($input['altitude']) && $input['altitude'] !== '' ? (float)$input['altitude'] : null;
$timezoneName = trim($input['timezone_name'] ?? '') ?: null;
$utcOffsetMinutes = isset($input['utc_offset_minutes']) && $input['utc_offset_minutes'] !== '' ? (int)$input['utc_offset_minutes'] : null;
$adminId = $_SESSION['admin']['id'];

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE solar_assessments SET
            client_name = ?, site_name = ?, country = ?, region = ?,
            latitude = ?, longitude = ?, altitude = ?, timezone_name = ?, utc_offset_minutes = ?
            WHERE id = ? AND admin_id = ?");
        $stmt->execute([$clientName, $siteName, $country, $region, $latitude, $longitude, $altitude, $timezoneName, $utcOffsetMinutes, $id, $adminId]);

        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare("SELECT id FROM solar_assessments WHERE id = ? AND admin_id = ?");
            $check->execute([$id, $adminId]);
            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Assessment not found.']);
                exit();
            }
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO solar_assessments
            (admin_id, client_name, site_name, country, region, latitude, longitude, altitude, timezone_name, utc_offset_minutes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$adminId, $clientName, $siteName, $country, $region, $latitude, $longitude, $altitude, $timezoneName, $utcOffsetMinutes]);
        $id = (int)$pdo->lastInsertId();
    }

    echo json_encode(['success' => true, 'id' => $id]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save this assessment right now.']);
}
