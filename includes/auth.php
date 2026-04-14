<?php
/**
 * Authentication Helper
 */

class Auth {

    public static function login(string $email, string $password): bool {
        $user = DB::fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$user) return false;

        if (!password_verify($password, $user['password'])) return false;

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        session_regenerate_id(true);
        return true;
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function check(): void {
        if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
        // Session timeout check
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
            self::logout();
            header('Location: ' . BASE_URL . '/login.php?timeout=1');
            exit;
        }
    }

    public static function isAdmin(): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public static function requireAdmin(): void {
        self::check();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Access denied.');
        }
    }

    public static function user(): array {
        return [
            'id'       => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? '',
            'email'    => $_SESSION['email'] ?? '',
            'role'     => $_SESSION['role'] ?? '',
        ];
    }
}
