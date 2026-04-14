<?php
// ============================================================
// JSON Response Helper
// ============================================================
class Response {
    public static function json(array $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(array $data = [], string $message = 'OK'): void {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function error(string $message, int $code = 400): void {
        self::json(['success' => false, 'error' => $message], $code);
    }

    public static function paginated(array $rows, int $total, int $page, int $limit): void {
        self::json([
            'success'      => true,
            'data'         => $rows,
            'meta'         => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => max(1, ceil($total / $limit)),
            ],
        ]);
    }
}
