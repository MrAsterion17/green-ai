<?php
// AJAX endpoint backing the Project & System Setup step: saves the PV system
// definition as a JSON snapshot on the solar_assessments row.
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
$projectName = trim($input['project_name'] ?? '');
$moduleType = trim($input['module_type'] ?? '');
$moduleWattage = isset($input['module_wattage']) && $input['module_wattage'] !== '' ? (float)$input['module_wattage'] : null;
$inverterType = trim($input['inverter_type'] ?? '');
$tableCount = isset($input['table_count']) && $input['table_count'] !== '' ? (int)$input['table_count'] : null;
$moduleCount = isset($input['module_count']) && $input['module_count'] !== '' ? (int)$input['module_count'] : null;
$systemSizeKwp = isset($input['system_size_kwp']) && $input['system_size_kwp'] !== '' ? (float)$input['system_size_kwp'] : null;

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Save the site details before configuring the system.']);
    exit();
}

if ($projectName === '' || $systemSizeKwp === null || $systemSizeKwp <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Project name and a system size (kWp) greater than zero are required.']);
    exit();
}

$config = [
    'project_name'     => $projectName,
    'module_type'      => $moduleType,
    'module_wattage_w' => $moduleWattage,
    'inverter_type'    => $inverterType,
    'table_count'      => $tableCount,
    'module_count'     => $moduleCount,
    'system_size_kwp'  => $systemSizeKwp,
];

try {
    $stmt = $pdo->prepare("UPDATE solar_assessments SET system_config = ?, status = 'system_configured' WHERE id = ? AND admin_id = ?");
    $stmt->execute([json_encode($config), $id, $_SESSION['admin']['id']]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare("SELECT id FROM solar_assessments WHERE id = ? AND admin_id = ?");
        $check->execute([$id, $_SESSION['admin']['id']]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Assessment not found.']);
            exit();
        }
    }

    echo json_encode(['success' => true, 'system_config' => $config]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save the system configuration right now.']);
}
