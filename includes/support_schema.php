<?php
// Lazily creates the tables backing After-Sales Support (admins + tickets),
// mirroring the ALTER-TABLE-on-demand pattern already used in login.php/register.php.
function greenai_ensure_support_tables(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        admin_key_hash VARCHAR(255) NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $columnCheck = $pdo->query("SHOW COLUMNS FROM admins LIKE 'admin_key_hash'");
    if ($columnCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN admin_key_hash VARCHAR(255) NOT NULL DEFAULT ''");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS support_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        category VARCHAR(20) NOT NULL,
        subject VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        admin_note TEXT NULL,
        resolved_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id)
    )");

    $columnCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login'");
    if ($columnCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL");
    }
}
