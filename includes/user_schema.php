<?php
// Lazily adds the home-address / SMS columns to `users` and creates the SMS log,
// mirroring the ALTER-TABLE-on-demand pattern used in support_schema.php.
function greenai_ensure_user_contact_columns(PDO $pdo) {
    $columns = [
        'block_no'   => "VARCHAR(20) NULL DEFAULT NULL",
        'lot_no'     => "VARCHAR(20) NULL DEFAULT NULL",
        'phone'      => "VARCHAR(20) NULL DEFAULT NULL",
        'sms_alerts' => "TINYINT(1) NOT NULL DEFAULT 1",
    ];
    foreach ($columns as $name => $definition) {
        if ($pdo->query("SHOW COLUMNS FROM users LIKE '$name'")->rowCount() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $name $definition");
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_alert_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        alert_type VARCHAR(30) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        status VARCHAR(20) NOT NULL,
        detail VARCHAR(255) NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id, alert_type, sent_at)
    )");
}

// The subdivisions residents can register under.
function greenai_subdivisions() {
    return ['Sentrina', 'Tierra Hermosa', 'St. Augustine Village'];
}
