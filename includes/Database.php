<?php
// ============================================================
// Database Singleton — PDO wrapper
// ============================================================
require_once __DIR__ . '/../config/db.php';

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
                 . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
            }
        }
        return self::$instance;
    }

    /**
     * Quick helper — execute a prepared statement and return the statement.
     */
    public static function run(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function lastInsertId(): string {
        return self::getInstance()->lastInsertId();
    }

    private function __construct() {}
    private function __clone()   {}
}
