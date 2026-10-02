<?php
// Backend proxy for the Live Solar Monitoring dashboard (admin/solar-monitoring.php).
//
// The frontend never talks to a vendor monitoring API directly — it only
// calls this endpoint. Swapping in a real inverter API (Sungrow iSolarCloud,
// SolarEdge, Growatt, SolarmanPV, ...) means adding your API keys as server
// config here and changing includes/solar_simulation.php's data functions to
// call out to that vendor instead of simulating — nothing on the frontend
// or in the JSON contract below needs to change.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_schema.php';
require_once __DIR__ . '/../includes/solar_monitoring_schema.php';
require_once __DIR__ . '/../includes/solar_simulation.php';
greenai_ensure_support_tables($pdo);
greenai_ensure_solar_monitoring_tables($pdo);

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Every signed-in admin can monitor every resident's system — these are
// staff-facing views, not plants an individual admin owns.
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function greenai_solar_load_plant(PDO $pdo, int $plantId): ?array {
    $stmt = $pdo->prepare("SELECT sp.*, u.fullname AS resident_name, u.email AS resident_email
        FROM solar_plants sp JOIN users u ON u.id = sp.user_id WHERE sp.id = ?");
    $stmt->execute([$plantId]);
    $plant = $stmt->fetch();
    return $plant ?: null;
}

switch ($action) {

    case 'plants': {
        greenai_ensure_all_resident_systems($pdo);
        $stmt = $pdo->query("SELECT sp.*, u.fullname AS resident_name, u.email AS resident_email
            FROM solar_plants sp JOIN users u ON u.id = sp.user_id ORDER BY u.fullname ASC");
        $plants = $stmt->fetchAll();
        echo json_encode(['plants' => $plants]);
        break;
    }

    case 'live': {
        $plantId = (int)($_GET['plant_id'] ?? 0);
        $plant = greenai_solar_load_plant($pdo, $plantId);
        if (!$plant) {
            http_response_code(404);
            echo json_encode(['error' => 'Plant not found.']);
            break;
        }
        $tz = new DateTimeZone($plant['timezone_name'] ?: 'Asia/Manila');
        $battery = greenai_chart_battery_soc_at($plant, new DateTimeImmutable('now', $tz));
        echo json_encode(['flow' => greenai_solar_live_flow($plant), 'battery' => $battery]);
        break;
    }

    case 'summary': {
        $plantId = (int)($_GET['plant_id'] ?? 0);
        $plant = greenai_solar_load_plant($pdo, $plantId);
        if (!$plant) {
            http_response_code(404);
            echo json_encode(['error' => 'Plant not found.']);
            break;
        }

        $range = $_GET['range'] ?? 'day';
        $allowedRanges = ['day', 'week', 'month', 'year', 'lifetime', 'custom'];
        if (!in_array($range, $allowedRanges, true)) {
            $range = 'day';
        }
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;

        $series = greenai_solar_series($plant, $range, $start, $end);
        $series['battery'] = greenai_energy_overview_series_battery($plant, $series['points'], $series['granularity']);

        $totals = greenai_solar_series_totals($series);
        $production = $totals['production'];
        $consumption = $totals['consumption'];
        $netEnergy = $production - $consumption;
        $tariff = (float)$plant['tariff_rate'];
        $netRevenue = $production * $tariff;

        $lifetimeKwh = greenai_solar_lifetime_production_kwh($plant);

        echo json_encode([
            'plant' => $plant,
            'range' => $range,
            'metrics' => [
                'production_kwh'  => round($production, 2),
                'consumption_kwh' => round($consumption, 2),
                'net_energy_kwh'  => round($netEnergy, 2),
                'net_revenue'     => round($netRevenue, 2),
                'tariff_rate'     => $tariff,
            ],
            'series' => $series,
            'environmental' => greenai_solar_environmental_impact($lifetimeKwh),
        ]);
        break;
    }

    case 'set_tariff': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $plantId = (int)($input['plant_id'] ?? 0);
        $tariff = $input['tariff_rate'] ?? null;

        if ($plantId <= 0 || $tariff === null || !is_numeric($tariff) || (float)$tariff < 0) {
            http_response_code(422);
            echo json_encode(['error' => 'A valid plant and tariff rate are required.']);
            break;
        }

        $plant = greenai_solar_load_plant($pdo, $plantId);
        if (!$plant) {
            http_response_code(404);
            echo json_encode(['error' => 'Plant not found.']);
            break;
        }

        $stmt = $pdo->prepare("UPDATE solar_plants SET tariff_rate = ? WHERE id = ?");
        $stmt->execute([(float)$tariff, $plantId]);

        echo json_encode(['success' => true, 'tariff_rate' => (float)$tariff]);
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action.']);
}
