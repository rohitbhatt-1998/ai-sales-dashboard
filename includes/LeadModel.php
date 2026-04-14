<?php
// ============================================================
// Lead Model
// ============================================================
require_once __DIR__ . '/Database.php';

class LeadModel {

    // ----------------------------------------------------------
    // List / Search
    // ----------------------------------------------------------
    public static function list(array $filters = [], int $page = 1, int $limit = 20): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['city'])) {
            $where[]  = 'city LIKE ?';
            $params[] = '%' . $filters['city'] . '%';
        }
        if (!empty($filters['q'])) {
            $where[]  = '(name LIKE ? OR phone LIKE ? OR email LIKE ?)';
            $q        = '%' . $filters['q'] . '%';
            $params   = array_merge($params, [$q, $q, $q]);
        }

        $whereSQL = implode(' AND ', $where);
        $offset   = ($page - 1) * $limit;

        $total = (int) Database::run("SELECT COUNT(*) FROM leads WHERE $whereSQL", $params)->fetchColumn();
        $rows  = Database::run(
            "SELECT * FROM leads WHERE $whereSQL ORDER BY created_at DESC LIMIT $limit OFFSET $offset",
            $params
        )->fetchAll();

        return ['rows' => $rows, 'total' => $total];
    }

    // ----------------------------------------------------------
    // Get single
    // ----------------------------------------------------------
    public static function find(int $id): ?array {
        $row = Database::run('SELECT * FROM leads WHERE id = ?', [$id])->fetch();
        return $row ?: null;
    }

    // ----------------------------------------------------------
    // Create
    // ----------------------------------------------------------
    public static function create(array $data): int {
        Database::run(
            'INSERT INTO leads (name, phone, email, city, company, status, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                trim($data['name']),
                trim($data['phone']),
                trim($data['email'] ?? ''),
                trim($data['city'] ?? ''),
                trim($data['company'] ?? ''),
                $data['status'] ?? 'New',
                trim($data['notes'] ?? ''),
                $data['created_by'] ?? null,
            ]
        );
        return (int) Database::lastInsertId();
    }

    // ----------------------------------------------------------
    // Update
    // ----------------------------------------------------------
    public static function update(int $id, array $data): void {
        $allowed = ['name','phone','email','city','company','status','notes','score'];
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
        Database::run("UPDATE leads SET " . implode(', ', $sets) . " WHERE id = ?", $params);
    }

    // ----------------------------------------------------------
    // Delete
    // ----------------------------------------------------------
    public static function delete(int $id): void {
        Database::run('DELETE FROM leads WHERE id = ?', [$id]);
    }

    // ----------------------------------------------------------
    // Bulk delete
    // ----------------------------------------------------------
    public static function bulkDelete(array $ids): int {
        if (empty($ids)) return 0;
        $ids  = array_map('intval', $ids);
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::run("DELETE FROM leads WHERE id IN ($ph)", $ids);
        return $stmt->rowCount();
    }

    // ----------------------------------------------------------
    // Import from CSV rows
    // ----------------------------------------------------------
    public static function importBatch(array $rows, int $userId): array {
        $imported = 0;
        $skipped  = 0;
        $db       = Database::getInstance();

        $stmt = $db->prepare(
            'INSERT IGNORE INTO leads (name, phone, email, city, company, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($rows as $row) {
            $phone = trim($row['phone'] ?? '');
            $name  = trim($row['name']  ?? '');
            if (!$phone || !$name) { $skipped++; continue; }

            // Dedup by phone
            $exists = Database::run('SELECT id FROM leads WHERE phone = ? LIMIT 1', [$phone])->fetchColumn();
            if ($exists) { $skipped++; continue; }

            $stmt->execute([
                $name,
                $phone,
                trim($row['email']   ?? ''),
                trim($row['city']    ?? ''),
                trim($row['company'] ?? ''),
                'New',
                $userId,
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    // ----------------------------------------------------------
    // Dashboard metrics
    // ----------------------------------------------------------
    public static function metrics(): array {
        $db = Database::getInstance();

        $total       = (int) $db->query("SELECT COUNT(*) FROM leads")->fetchColumn();
        $hot         = (int) $db->query("SELECT COUNT(*) FROM leads WHERE status='Hot'")->fetchColumn();
        $callsToday  = (int) $db->query("SELECT COUNT(*) FROM calls WHERE DATE(created_at)=CURDATE()")->fetchColumn();
        $connected   = (int) $db->query("SELECT COUNT(*) FROM calls WHERE status='Connected' AND DATE(created_at)=CURDATE()")->fetchColumn();

        // Conversion = leads that became Hot / total called leads
        $totalCalled = (int) $db->query("SELECT COUNT(DISTINCT lead_id) FROM calls")->fetchColumn();
        $converted   = (int) $db->query("SELECT COUNT(*) FROM leads WHERE status='Hot'")->fetchColumn();
        $convRate    = $totalCalled > 0 ? round(($converted / $totalCalled) * 100, 1) : 0;

        return [
            'total_leads'     => $total,
            'calls_today'     => $callsToday,
            'connected_today' => $connected,
            'hot_leads'       => $hot,
            'conversion_rate' => $convRate,
        ];
    }
}
