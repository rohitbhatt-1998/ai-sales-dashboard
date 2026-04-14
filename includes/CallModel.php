<?php
// ============================================================
// Call Model
// ============================================================
require_once __DIR__ . '/Database.php';

class CallModel {

    // ----------------------------------------------------------
    // Create a new call record
    // ----------------------------------------------------------
    public static function create(int $leadId, int $attempt = 1): int {
        Database::run(
            'INSERT INTO calls (lead_id, status, attempt, started_at) VALUES (?, ?, ?, NOW())',
            [$leadId, 'Calling', $attempt]
        );
        return (int) Database::lastInsertId();
    }

    // ----------------------------------------------------------
    // Update call
    // ----------------------------------------------------------
    public static function update(int $id, array $data): void {
        $allowed = ['status','duration','transcript','summary','ai_score',
                    'sentiment','twilio_call_sid','recording_url','notes','ended_at'];
        $sets    = [];
        $params  = [];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]  = "$col = ?";
                $params[] = $data[$col];
            }
        }
        if (empty($sets)) return;

        $params[] = $id;
        Database::run("UPDATE calls SET " . implode(', ', $sets) . " WHERE id = ?", $params);
    }

    // ----------------------------------------------------------
    // Find
    // ----------------------------------------------------------
    public static function find(int $id): ?array {
        $row = Database::run(
            'SELECT c.*, l.name AS lead_name, l.phone AS lead_phone, l.city AS lead_city
             FROM calls c
             LEFT JOIN leads l ON l.id = c.lead_id
             WHERE c.id = ?',
            [$id]
        )->fetch();
        return $row ?: null;
    }

    // ----------------------------------------------------------
    // List with filters
    // ----------------------------------------------------------
    public static function list(array $filters = [], int $page = 1, int $limit = 20): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['lead_id'])) {
            $where[]  = 'c.lead_id = ?';
            $params[] = (int) $filters['lead_id'];
        }
        if (!empty($filters['status'])) {
            $where[]  = 'c.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'DATE(c.created_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'DATE(c.created_at) <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['q'])) {
            $where[]  = '(l.name LIKE ? OR l.phone LIKE ?)';
            $q        = '%' . $filters['q'] . '%';
            $params   = array_merge($params, [$q, $q]);
        }

        $whereSQL = implode(' AND ', $where);
        $offset   = ($page - 1) * $limit;

        $total = (int) Database::run(
            "SELECT COUNT(*) FROM calls c LEFT JOIN leads l ON l.id=c.lead_id WHERE $whereSQL",
            $params
        )->fetchColumn();

        $rows = Database::run(
            "SELECT c.*, l.name AS lead_name, l.phone AS lead_phone, l.city AS lead_city
             FROM calls c
             LEFT JOIN leads l ON l.id = c.lead_id
             WHERE $whereSQL
             ORDER BY c.created_at DESC
             LIMIT $limit OFFSET $offset",
            $params
        )->fetchAll();

        return ['rows' => $rows, 'total' => $total];
    }

    // ----------------------------------------------------------
    // Count attempts for lead
    // ----------------------------------------------------------
    public static function countAttempts(int $leadId): int {
        return (int) Database::run(
            "SELECT COUNT(*) FROM calls WHERE lead_id = ?",
            [$leadId]
        )->fetchColumn();
    }

    // ----------------------------------------------------------
    // Recent calls for dashboard
    // ----------------------------------------------------------
    public static function recentCalls(int $limit = 10): array {
        return Database::run(
            "SELECT c.*, l.name AS lead_name, l.phone AS lead_phone, l.city AS lead_city
             FROM calls c
             LEFT JOIN leads l ON l.id = c.lead_id
             ORDER BY c.created_at DESC
             LIMIT ?",
            [$limit]
        )->fetchAll();
    }

    // ----------------------------------------------------------
    // Delete
    // ----------------------------------------------------------
    public static function delete(int $id): void {
        Database::run('DELETE FROM calls WHERE id = ?', [$id]);
    }
}
