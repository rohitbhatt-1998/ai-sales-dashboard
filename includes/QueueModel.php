<?php
// ============================================================
// Queue Model
// ============================================================
require_once __DIR__ . '/Database.php';

class QueueModel {

    // ----------------------------------------------------------
    // Add leads to queue (dedup check)
    // ----------------------------------------------------------
    public static function addLeads(array $leadIds, int $maxAttempts = 2): array {
        $added    = 0;
        $skipped  = 0;

        foreach ($leadIds as $leadId) {
            $leadId = (int) $leadId;
            if (!$leadId) { $skipped++; continue; }

            // Skip if already pending / processing for this lead
            $exists = Database::run(
                "SELECT id FROM call_queue WHERE lead_id = ? AND status IN ('Pending','Processing') LIMIT 1",
                [$leadId]
            )->fetchColumn();

            if ($exists) { $skipped++; continue; }

            // Check max attempts
            $attempts = (int) Database::run(
                "SELECT COUNT(*) FROM calls WHERE lead_id = ?",
                [$leadId]
            )->fetchColumn();

            if ($attempts >= $maxAttempts) { $skipped++; continue; }

            Database::run(
                'INSERT INTO call_queue (lead_id, max_attempts, attempt, status)
                 VALUES (?, ?, ?, ?)',
                [$leadId, $maxAttempts, $attempts + 1, 'Pending']
            );
            $added++;
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    // ----------------------------------------------------------
    // Get next pending item
    // ----------------------------------------------------------
    public static function getNext(): ?array {
        $row = Database::run(
            "SELECT * FROM call_queue
             WHERE status = 'Pending'
               AND (scheduled_at IS NULL OR scheduled_at <= NOW())
             ORDER BY priority DESC, id ASC
             LIMIT 1"
        )->fetch();
        return $row ?: null;
    }

    // ----------------------------------------------------------
    // Mark processing
    // ----------------------------------------------------------
    public static function markProcessing(int $id): void {
        Database::run(
            "UPDATE call_queue SET status='Processing', processed_at=NOW() WHERE id=?",
            [$id]
        );
    }

    // ----------------------------------------------------------
    // Mark done
    // ----------------------------------------------------------
    public static function markDone(int $id, int $callId): void {
        Database::run(
            "UPDATE call_queue SET status='Done', call_id=? WHERE id=?",
            [$callId, $id]
        );
    }

    // ----------------------------------------------------------
    // Retry or fail
    // ----------------------------------------------------------
    public static function retryOrFail(int $id, string $reason, int $delaySecs = 60): void {
        $item = Database::run('SELECT * FROM call_queue WHERE id = ?', [$id])->fetch();
        if (!$item) return;

        if ($item['attempt'] < $item['max_attempts']) {
            Database::run(
                "UPDATE call_queue SET status='Pending', attempt=attempt+1, fail_reason=?,
                 scheduled_at=DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id=?",
                [$reason, $delaySecs, $id]
            );
        } else {
            Database::run(
                "UPDATE call_queue SET status='Failed', fail_reason=? WHERE id=?",
                [$reason, $id]
            );
        }
    }

    // ----------------------------------------------------------
    // Queue summary
    // ----------------------------------------------------------
    public static function summary(): array {
        $rows = Database::run(
            "SELECT status, COUNT(*) AS cnt FROM call_queue GROUP BY status"
        )->fetchAll();

        $result = ['Pending' => 0, 'Processing' => 0, 'Done' => 0, 'Failed' => 0, 'Skipped' => 0];
        foreach ($rows as $r) {
            $result[$r['status']] = (int) $r['cnt'];
        }
        return $result;
    }

    // ----------------------------------------------------------
    // List queue items with lead info
    // ----------------------------------------------------------
    public static function list(array $filters = [], int $page = 1, int $limit = 20): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'q.status = ?';
            $params[] = $filters['status'];
        }

        $whereSQL = implode(' AND ', $where);
        $offset   = ($page - 1) * $limit;

        $total = (int) Database::run(
            "SELECT COUNT(*) FROM call_queue q WHERE $whereSQL",
            $params
        )->fetchColumn();

        $rows = Database::run(
            "SELECT q.*, l.name AS lead_name, l.phone AS lead_phone
             FROM call_queue q
             LEFT JOIN leads l ON l.id = q.lead_id
             WHERE $whereSQL
             ORDER BY q.id DESC
             LIMIT $limit OFFSET $offset",
            $params
        )->fetchAll();

        return ['rows' => $rows, 'total' => $total];
    }

    // ----------------------------------------------------------
    // Clear done / failed items
    // ----------------------------------------------------------
    public static function clearFinished(): int {
        $stmt = Database::run(
            "DELETE FROM call_queue WHERE status IN ('Done','Failed','Skipped')"
        );
        return $stmt->rowCount();
    }
}
