<?php
/**
 * Database Connection (PDO Singleton)
 */

class DB {
    private static ?PDO $instance = null;

    public static function connect(): PDO {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('DB Connection Failed: ' . $e->getMessage());
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed.']));
            }
        }
        return self::$instance;
    }

    // Shortcut: run a query and return all rows
    public static function fetchAll(string $sql, array $params = []): array {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Shortcut: return single row
    public static function fetchOne(string $sql, array $params = []): ?array {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Shortcut: execute insert/update/delete, return affected rows
    public static function execute(string $sql, array $params = []): int {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    // Shortcut: insert and return last insert ID
    public static function insert(string $sql, array $params = []): int {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return (int) self::connect()->lastInsertId();
    }

    // Fetch config value
    public static function getConfig(string $key, string $default = ''): string {
        $row = self::fetchOne('SELECT config_value FROM ai_config WHERE config_key = ?', [$key]);
        return $row ? $row['config_value'] : $default;
    }

    // Set config value
    public static function setConfig(string $key, string $value): void {
        self::execute(
            'INSERT INTO ai_config (config_key, config_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)',
            [$key, $value]
        );
    }
}
