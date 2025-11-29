<?php
/**
 * Database Configuration for YourTrip Analytics
 *
 * Connection via SSH tunnel:
 * ssh -L 33060:127.0.0.1:3306 inf2003-dev@35.212.180.159
 */

// Database credentials
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'yourtrip_db');
define('DB_USER', 'inf2003-sqldev');
define('DB_PASS', 'Inf2003#DevSecure!2025');

/**
 * Get database connection using PDO
 *
 * @return PDO Database connection object
 * @throws PDOException if connection fails
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'"
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;

    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Convert BIGINT values to numbers for JSON encoding
 *
 * @param mixed $value Value to convert
 * @return mixed Converted value
 */
function convertBigInt($value) {
    if (is_numeric($value) && strlen($value) > 9) {
        return (float) $value;
    }
    return $value;
}
?>
