<?php
// Texts opted-in residents when rain is forecast soon so they harvest/save solar energy.
// Meant to be called by a scheduler every ~15-30 minutes:
//   https://<domain>/api/rainalert.php?key=<CRON_SECRET>
// Add &dry=1 to see who would be texted without sending anything.
header('Content-Type: application/json');

$secret = getenv('CRON_SECRET');
if (!$secret) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'CRON_SECRET is not configured on the server.']);
    exit;
}
$given = $_GET['key'] ?? ($_SERVER['HTTP_X_CRON_KEY'] ?? '');
if (!hash_equals($secret, (string) $given)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/user_schema.php';
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../includes/rain_alert.php';

greenai_ensure_user_contact_columns($pdo);
$dry = !empty($_GET['dry']);

$rain = greenai_upcoming_rain();
if (!$rain) {
    echo json_encode(['status' => 'ok', 'rain' => false, 'sent' => 0]);
    exit;
}

$message = greenai_rain_sms_text($rain);

$stmt = $pdo->prepare("
    SELECT u.id, u.phone FROM users u
    WHERE u.sms_alerts = 1 AND u.phone IS NOT NULL AND u.phone <> ''
      AND NOT EXISTS (
          SELECT 1 FROM sms_alert_log l
          WHERE l.user_id = u.id AND l.alert_type = 'rain' AND l.status = 'sent'
            AND l.sent_at > (NOW() - INTERVAL " . (int) GREENAI_RAIN_COOLDOWN_HOURS . " HOUR)
      )
");
$stmt->execute();
$recipients = $stmt->fetchAll();

$log = $pdo->prepare("INSERT INTO sms_alert_log (user_id, alert_type, phone, status, detail) VALUES (?, 'rain', ?, ?, ?)");
$sent = 0;
$failed = 0;
foreach ($recipients as $r) {
    if ($dry) {
        continue;
    }
    $result = greenai_send_sms($r['phone'], $message);
    $log->execute([$r['id'], $r['phone'], $result['status'], $result['detail']]);
    if ($result['ok']) {
        $sent++;
    } else {
        $failed++;
    }
}

echo json_encode([
    'status'     => 'ok',
    'rain'       => true,
    'forecast'   => $rain,
    'message'    => $message,
    'recipients' => count($recipients),
    'sent'       => $sent,
    'failed'     => $failed,
    'dry_run'    => $dry,
]);
