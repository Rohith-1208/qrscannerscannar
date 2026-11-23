<?php
// ==============================================================================
// Database Connection Manager (PDO Singleton)
// ==============================================================================

require_once __DIR__ . '/constants.php';

class Database {
    private static ?PDO $instance = null;

    /**
     * Get single PDO database connection instance.
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("Database connection failure: " . $e->getMessage());
                jsonResponse([
                    'success' => false,
                    'error'   => 'Database connection failed. Please verify that MySQL service is running and credentials are correct.'
                ], 500);
            }
        }

        return self::$instance;
    }

    /**
     * Prevent instantiation and cloning.
     */
    private function __construct() {}
    private function __clone() {}
}
