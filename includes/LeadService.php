<?php
/**
 * Lead Service — CRUD + CSV import
 */

class LeadService {

    // -------------------------------------------------------
    // Get leads with filters + pagination
    // -------------------------------------------------------
    public static function getLeads(array $filters = [], int $page = 1, int $perPage = 20): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'l.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['city'])) {
            $where[]  = 'l.city LIKE ?';
            $params[] = '%' . $filters['city'] . '%';
        }
        if (!empty($filters['search'])) {
            $where[]  = '(l.name LIKE ? OR l.phone LIKE ? OR l.email LIKE ?)';
            $s = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $whereStr = implode(' AND ', $where);

        $total = DB::fetchOne("SELECT COUNT(*) as cnt FROM leads l WHERE {$whereStr}", $params)['cnt'] ?? 0;
        $pg    = paginate($total, $perPage, $page);

        $leads = DB::fetchAll(
            "SELECT l.*,
                (SELECT COUNT(*) FROM call_logs cl WHERE cl.lead_id = l.id) as call_count
             FROM leads l
             WHERE {$whereStr}
             ORDER BY l.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $pg['offset']])
        );

        return ['leads' => $leads, 'pagination' => $pg];
    }

    // -------------------------------------------------------
    // Get single lead
    // -------------------------------------------------------
    public static function getLead(int $id): ?array {
        return DB::fetchOne('SELECT * FROM leads WHERE id = ?', [$id]);
    }

    // -------------------------------------------------------
    // Create lead
    // -------------------------------------------------------
    public static function createLead(array $data): array {
        // Validate
        $errors = self::validate($data);
        if ($errors) return ['success' => false, 'errors' => $errors];

        // Check duplicate phone
        $exists = DB::fetchOne('SELECT id FROM leads WHERE phone = ?', [formatPhone($data['phone'])]);
        if ($exists) return ['success' => false, 'errors' => ['phone' => 'This phone number already exists.']];

        $id = DB::insert(
            'INSERT INTO leads (name, phone, email, city, company, notes, status) VALUES (?, ?, ?, ?, ?, ?, "new")',
            [
                sanitize($data['name']),
                formatPhone($data['phone']),
                sanitize($data['email'] ?? ''),
                sanitize($data['city'] ?? ''),
                sanitize($data['company'] ?? ''),
                sanitize($data['notes'] ?? ''),
            ]
        );

        return ['success' => true, 'id' => $id];
    }

    // -------------------------------------------------------
    // Update lead
    // -------------------------------------------------------
    public static function updateLead(int $id, array $data): array {
        $errors = self::validate($data);
        if ($errors) return ['success' => false, 'errors' => $errors];

        DB::execute(
            'UPDATE leads SET name = ?, phone = ?, email = ?, city = ?, company = ?, notes = ?, updated_at = NOW() WHERE id = ?',
            [
                sanitize($data['name']),
                formatPhone($data['phone']),
                sanitize($data['email'] ?? ''),
                sanitize($data['city'] ?? ''),
                sanitize($data['company'] ?? ''),
                sanitize($data['notes'] ?? ''),
                $id,
            ]
        );

        return ['success' => true];
    }

    // -------------------------------------------------------
    // Delete lead
    // -------------------------------------------------------
    public static function deleteLead(int $id): bool {
        return DB::execute('DELETE FROM leads WHERE id = ?', [$id]) > 0;
    }

    // -------------------------------------------------------
    // Import CSV
    // -------------------------------------------------------
    public static function importCsv(string $filePath): array {
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'File not found.'];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['success' => false, 'error' => 'Cannot read file.'];
        }

        $headers   = null;
        $rowNum    = 0;
        $maxRows   = MAX_CSV_ROWS;

        while (($row = fgetcsv($handle, 1000, ',')) !== false && $rowNum < $maxRows) {
            $rowNum++;

            // First row = headers
            if ($headers === null) {
                $headers = array_map('strtolower', array_map('trim', $row));
                continue;
            }

            // Map columns
            $data = array_combine($headers, array_pad($row, count($headers), ''));

            $name  = trim($data['name'] ?? $data['full name'] ?? '');
            $phone = trim($data['phone'] ?? $data['mobile'] ?? $data['phone number'] ?? '');
            $email = trim($data['email'] ?? '');
            $city  = trim($data['city'] ?? $data['location'] ?? '');
            $company = trim($data['company'] ?? $data['organization'] ?? '');

            if (!$name || !$phone) {
                $skipped++;
                $errors[] = "Row {$rowNum}: Missing name or phone.";
                continue;
            }

            $phone = formatPhone($phone);

            // Skip duplicates
            $exists = DB::fetchOne('SELECT id FROM leads WHERE phone = ?', [$phone]);
            if ($exists) {
                $skipped++;
                continue;
            }

            DB::insert(
                'INSERT INTO leads (name, phone, email, city, company, status) VALUES (?, ?, ?, ?, ?, "new")',
                [$name, $phone, $email, $city, $company]
            );
            $imported++;
        }

        fclose($handle);
        @unlink($filePath);

        return [
            'success'  => true,
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 10),
        ];
    }

    // -------------------------------------------------------
    // Validate lead data
    // -------------------------------------------------------
    private static function validate(array $data): array {
        $errors = [];
        if (empty($data['name'])) $errors['name'] = 'Name is required.';
        if (empty($data['phone'])) {
            $errors['phone'] = 'Phone is required.';
        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $data['phone'])) {
            $errors['phone'] = 'Invalid phone number format.';
        }
        return $errors;
    }

    // -------------------------------------------------------
    // Get dashboard metrics
    // -------------------------------------------------------
    public static function getDashboardMetrics(): array {
        $today = date('Y-m-d');

        return [
            'total_leads'     => DB::fetchOne('SELECT COUNT(*) as c FROM leads')['c'] ?? 0,
            'calls_today'     => DB::fetchOne('SELECT COUNT(*) as c FROM call_logs WHERE DATE(created_at) = ?', [$today])['c'] ?? 0,
            'connected_calls' => DB::fetchOne('SELECT COUNT(*) as c FROM call_logs WHERE status = "completed"')['c'] ?? 0,
            'hot_leads'       => DB::fetchOne('SELECT COUNT(*) as c FROM leads WHERE status = "hot"')['c'] ?? 0,
            'warm_leads'      => DB::fetchOne('SELECT COUNT(*) as c FROM leads WHERE status = "warm"')['c'] ?? 0,
            'cold_leads'      => DB::fetchOne('SELECT COUNT(*) as c FROM leads WHERE status = "cold"')['c'] ?? 0,
            'conversion_rate' => self::getConversionRate(),
            'queue_pending'   => DB::fetchOne('SELECT COUNT(*) as c FROM call_queue WHERE status = "pending"')['c'] ?? 0,
            'new_leads'       => DB::fetchOne('SELECT COUNT(*) as c FROM leads WHERE status = "new"')['c'] ?? 0,
        ];
    }

    private static function getConversionRate(): string {
        $total   = DB::fetchOne('SELECT COUNT(*) as c FROM call_logs WHERE status = "completed"')['c'] ?? 0;
        $hot     = DB::fetchOne('SELECT COUNT(*) as c FROM call_logs WHERE lead_score = "hot"')['c'] ?? 0;
        if (!$total) return '0%';
        return round(($hot / $total) * 100, 1) . '%';
    }

    // -------------------------------------------------------
    // Get recent call activity (for dashboard chart)
    // -------------------------------------------------------
    public static function getCallActivity(int $days = 7): array {
        $rows = DB::fetchAll(
            'SELECT DATE(created_at) as date, COUNT(*) as total,
                SUM(status = "completed") as completed,
                SUM(status = "no_answer") as no_answer
             FROM call_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC',
            [$days]
        );
        return $rows;
    }
}
