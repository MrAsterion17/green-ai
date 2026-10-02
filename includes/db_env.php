<?php
// Database settings read from environment variables.
// On Railway, link the MySQL service so MYSQLHOST, MYSQLPORT, MYSQLUSER,
// MYSQLPASSWORD and MYSQLDATABASE are available to this service.
// Locally (XAMPP) none are set, so the defaults below are used.

if (!function_exists('greenai_env')) {
    function greenai_env(array $names, $default) {
        foreach ($names as $name) {
            $value = getenv($name);
            if ($value !== false && $value !== '') {
                return $value;
            }
        }
        return $default;
    }
}

if (!defined('GREENAI_DB_HOST')) {
    define('GREENAI_DB_HOST', greenai_env(['MYSQLHOST', 'DB_HOST'], 'localhost'));
    define('GREENAI_DB_PORT', (int) greenai_env(['MYSQLPORT', 'DB_PORT'], 3306));
    define('GREENAI_DB_NAME', greenai_env(['MYSQLDATABASE', 'DB_NAME'], 'greenai_db'));
    define('GREENAI_DB_USER', greenai_env(['MYSQLUSER', 'DB_USER'], 'root'));
    define('GREENAI_DB_PASS', greenai_env(['MYSQLPASSWORD', 'DB_PASSWORD'], ''));
}
