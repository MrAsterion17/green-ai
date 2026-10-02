<?php
// AJAX endpoint backing the Site Lookup step: proxies Open-Meteo's geocoding
// API server-side (no key involved) and returns candidate sites as JSON.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/geo.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode(['results' => []]);
    exit();
}

try {
    $results = greenai_geo_search($q);
    echo json_encode(['results' => $results]);
} catch (Exception $e) {
    http_response_code(502);
    echo json_encode(['error' => 'Site lookup service is unavailable right now.']);
}
