<?php
// Lazily creates the table backing the Live Solar Monitoring dashboard,
// mirroring the ALTER-TABLE-on-demand pattern used in support_schema.php.
//
// Each row is one resident's home rooftop system (residential scale, not a
// utility plant) — one system per user, auto-provisioned on first view.
function greenai_ensure_solar_monitoring_tables(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS solar_plants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        capacity_kw DECIMAL(10,2) NOT NULL,
        system_type VARCHAR(20) NOT NULL DEFAULT 'grid_tied',
        location_label VARCHAR(150) NULL,
        timezone_name VARCHAR(64) NOT NULL DEFAULT 'Asia/Manila',
        tariff_rate DECIMAL(10,4) NOT NULL DEFAULT 11.5000,
        commissioned_at DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (user_id)
    )");

    // Migrate any earlier admin-owned-utility-plant install to the
    // per-resident schema (this dashboard used to model one big admin
    // plant instead of one small system per household).
    $hasAdminId = $pdo->query("SHOW COLUMNS FROM solar_plants LIKE 'admin_id'")->rowCount() > 0;
    if ($hasAdminId) {
        $pdo->exec("DELETE FROM solar_plants"); // old rows modeled a utility plant, not a resident's home
        $pdo->exec("ALTER TABLE solar_plants DROP COLUMN admin_id");
    }
    if ($pdo->query("SHOW COLUMNS FROM solar_plants LIKE 'user_id'")->rowCount() === 0) {
        $pdo->exec("ALTER TABLE solar_plants ADD COLUMN user_id INT NOT NULL AFTER id, ADD UNIQUE KEY (user_id)");
    }
}

// Auto-provisions a small residential rooftop system (3-10kWp) for one
// resident, deterministic from their user id so repeated calls are stable.
function greenai_ensure_resident_system(PDO $pdo, int $userId, string $fullname, ?string $residence = null): void {
    $stmt = $pdo->prepare("SELECT id FROM solar_plants WHERE user_id = ?");
    $stmt->execute([$userId]);
    if ($stmt->fetch()) {
        return;
    }

    $seed = crc32('resident-system|' . $userId);
    $capacityKw = round(3 + (($seed % 1000) / 1000) * 7, 2); // 3.00 - 10.00 kWp
    $typeRoll = $seed % 100;
    $systemType = $typeRoll < 80 ? 'grid_tied' : ($typeRoll < 95 ? 'hybrid' : 'off_grid');
    $commissionedDaysAgo = 30 + ($seed % 1000); // spread install dates over the last ~3 years

    $stmt = $pdo->prepare("INSERT INTO solar_plants
        (user_id, name, capacity_kw, system_type, location_label, timezone_name, tariff_rate, commissioned_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $fullname . "'s Home System",
        $capacityKw,
        $systemType,
        $residence ?: null,
        'Asia/Manila',
        11.5000,
        date('Y-m-d', strtotime("-$commissionedDaysAgo days")),
    ]);
}

// Provisions a system for every registered resident so the admin's monitor
// list is complete (cheap: one existence check per resident, skips anyone
// already provisioned).
function greenai_ensure_all_resident_systems(PDO $pdo): void {
    $stmt = $pdo->query("SELECT id, fullname, Residence FROM users");
    foreach ($stmt->fetchAll() as $u) {
        greenai_ensure_resident_system($pdo, (int)$u['id'], $u['fullname'], $u['Residence'] ?? null);
    }
}
