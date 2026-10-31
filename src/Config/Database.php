<?php

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database Connection Manager (PDO)
 *
 * Implements a thread-safe singleton connection to MySQL with strict
 * error reporting and disabled prepared statement emulation.
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
    }

    /**
     * Get or initialize the active PDO connection instance
     *
     * @return PDO
     * @throws RuntimeException If connection cannot be established
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }

        return self::$instance;
    }

    /**
     * Establish a new PDO connection using environment variables
     *
     * @return PDO
     * @throws RuntimeException
     */
    private static function createConnection(): PDO
    {
        // Environment variable discovery with fallbacks
        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '3306';
        $dbName = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'inventory_ledger');
        $user = getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: 'app_user');
        $password = getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: 'app_secret_password');
        $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

        $options = [
            // Always throw exceptions on error
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Return associative arrays by default
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Disable emulated prepared statements for genuine server-side parameterized queries
            PDO::ATTR_EMULATE_PREPARES => false,
            // Connection timeout in seconds
            PDO::ATTR_TIMEOUT => 5,
        ];

        try {
            $pdo = new PDO($dsn, $user, $password, $options);
            return $pdo;
        } catch (PDOException $e) {
            error_log(sprintf('[Database Error] Connection failed: %s', $e->getMessage()));
            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Test connection health and return diagnostic status
     *
     * @return array{success: bool, message: string}
     */
    public static function checkConnection(): array
    {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query('SELECT 1');
            $stmt->fetch();
            return ['success' => true, 'message' => 'Connection established successfully'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Close the current connection instance (useful for testing or worker scripts)
     */
    public static function closeConnection(): void
    {
        self::$instance = null;
    }
}

