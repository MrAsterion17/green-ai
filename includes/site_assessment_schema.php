<?php
// Lazily creates the table backing the Solar Site Assessment feature,
// mirroring the ALTER-TABLE-on-demand pattern used in support_schema.php.
function greenai_ensure_site_assessment_tables(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS solar_assessments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        client_name VARCHAR(150) NULL,
        site_name VARCHAR(150) NOT NULL,
        country VARCHAR(100) NULL,
        region VARCHAR(100) NULL,
        latitude DECIMAL(10,6) NOT NULL,
        longitude DECIMAL(10,6) NOT NULL,
        altitude DECIMAL(8,2) NULL,
        timezone_name VARCHAR(64) NULL,
        utc_offset_minutes INT NULL,
        weather_data LONGTEXT NULL,
        system_config LONGTEXT NULL,
        results LONGTEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (admin_id)
    )");
}
