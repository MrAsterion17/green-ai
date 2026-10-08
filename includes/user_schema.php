<?php
// Lazily adds the home-address (block / lot) columns to `users` mirroring the ALTER-TABLE-on-demand pattern used in support_schema.php.
function greenai_ensure_user_contact_columns(PDO $pdo) {
    $columns = [
        'block_no'   => "VARCHAR(20) NULL DEFAULT NULL",
        'lot_no'     => "VARCHAR(20) NULL DEFAULT NULL",
    ];
    foreach ($columns as $name => $definition) {
        if ($pdo->query("SHOW COLUMNS FROM users LIKE '$name'")->rowCount() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $name $definition");
        }
    }
}

// The subdivisions residents can register under.
function greenai_subdivisions() {
    return ['Sentrina', 'Tierra Hermosa', 'St. Augustine Village'];
}
