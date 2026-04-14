<?php
// ============================================================
// Auth — session-based authentication
// ============================================================
require_once __DIR__ . '/Database.php';

class Auth {

    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('ai_sales_sess');
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function login(string $email, string $password): array {
        $row = Database::run(
            'SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1',
            [strtolower(trim($email))]
        )->fetch();

        if (!$row || !password_verify($password, $row['password'])) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        self::startSession();
        $_SESSION['user_id']   = $row['id'];
        $_SESSION['user_name'] = $row['name'];
        $_SESSION['user_role'] = $row['role'];
        $_SESSION['logged_in'] = true;
        session_regenerate_id(true);

        Database::run('UPDATE users SET last_login = NOW() WHERE id = ?', [$row['id']]);
        return ['success' => true, 'user' => ['id' => $row['id'], 'name' => $row['name'], 'role' => $row['role']]];
    }

    public static function logout(): void {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function isLoggedIn(): bool {
        self::startSession();
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthenticated']);
            exit;
        }
    }

    public static function currentUser(): array {
        self::startSession();
        return [
            'id'   => $_SESSION['user_id']   ?? null,
            'name' => $_SESSION['user_name'] ?? null,
            'role' => $_SESSION['user_role'] ?? null,
        ];
    }

    /**
     * Create the default admin account (called from install.php).
     */
    public static function createAdmin(string $name, string $email, string $password): int {
        Database::run(
            'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
            [$name, strtolower($email), password_hash($password, PASSWORD_DEFAULT), 'admin']
        );
        return (int) Database::lastInsertId();
    }
}
