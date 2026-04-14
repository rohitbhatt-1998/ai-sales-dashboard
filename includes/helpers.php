<?php
/**
 * General Helper Functions
 */

// Clean output for HTML
function h(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

// JSON response and exit
function jsonResponse(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Sanitize string input
function sanitize(string $input): string {
    return trim(strip_tags($input));
}

// Format duration (seconds → mm:ss or hh:mm:ss)
function formatDuration(int $seconds): string {
    if ($seconds < 3600) {
        return gmdate('i:s', $seconds);
    }
    return gmdate('H:i:s', $seconds);
}

// Format date
function formatDate(string $datetime, string $format = 'd M Y, h:i A'): string {
    if (!$datetime) return '—';
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

// Generate CSRF token
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Status badge HTML
function statusBadge(string $status): string {
    $map = [
        'new'        => ['New',        'badge-new'],
        'calling'    => ['Calling',    'badge-calling'],
        'connected'  => ['Connected',  'badge-connected'],
        'no_answer'  => ['No Answer',  'badge-noanswer'],
        'called'     => ['Called',     'badge-called'],
        'hot'        => ['🔥 Hot',     'badge-hot'],
        'warm'       => ['🌤 Warm',    'badge-warm'],
        'cold'       => ['❄️ Cold',    'badge-cold'],
        'queued'     => ['Queued',     'badge-queued'],
        'failed'     => ['Failed',     'badge-failed'],
        'completed'  => ['Completed',  'badge-completed'],
    ];
    $label = $map[$status][0] ?? ucfirst($status);
    $cls   = $map[$status][1] ?? 'badge-default';
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

// Phone number formatting (basic)
function formatPhone(string $phone): string {
    $clean = preg_replace('/[^0-9+]/', '', $phone);
    return $clean;
}

// Truncate text
function truncate(string $text, int $length = 100): string {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '…';
}

// Pagination helper
function paginate(int $total, int $perPage, int $current): array {
    $totalPages = (int) ceil($total / $perPage);
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $current,
        'total_pages' => $totalPages,
        'offset'      => ($current - 1) * $perPage,
        'has_prev'    => $current > 1,
        'has_next'    => $current < $totalPages,
    ];
}

// Lead score classifier based on call transcript keywords
function classifyLeadScore(string $transcript): string {
    if (empty($transcript)) return 'cold';

    $transcript = strtolower($transcript);

    $hotKeywords  = ['interested', 'yes', 'definitely', 'buy', 'purchase', 'when can', 'how much', 'price', 'demo', 'book', 'schedule', 'call back', 'proceed'];
    $warmKeywords = ['maybe', 'possibly', 'think about', 'consider', 'later', 'sometime', 'not sure', 'let me check'];
    $coldKeywords = ['not interested', 'no thank', 'busy', 'remove', 'stop calling', 'do not call', 'wrong number'];

    $hotScore  = 0;
    $warmScore = 0;
    $coldScore = 0;

    foreach ($hotKeywords as $kw) {
        if (strpos($transcript, $kw) !== false) $hotScore++;
    }
    foreach ($warmKeywords as $kw) {
        if (strpos($transcript, $kw) !== false) $warmScore++;
    }
    foreach ($coldKeywords as $kw) {
        if (strpos($transcript, $kw) !== false) $coldScore++;
    }

    if ($coldScore > 0) return 'cold';
    if ($hotScore >= 2) return 'hot';
    if ($hotScore >= 1 || $warmScore >= 1) return 'warm';

    return 'cold';
}

// Generate AI summary from transcript (mock for non-AI mode)
function generateMockSummary(string $transcript, string $leadName): string {
    if (empty($transcript)) {
        return 'Call was not connected. No conversation recorded.';
    }
    $score = classifyLeadScore($transcript);
    $summaries = [
        'hot'  => "Lead {$leadName} showed strong interest. Ready to move forward. Schedule a follow-up demo immediately.",
        'warm' => "Lead {$leadName} expressed moderate interest. Needs more nurturing. Follow up within 48 hours.",
        'cold' => "Lead {$leadName} was not interested at this time. Consider removing or re-engaging after 30 days.",
    ];
    return $summaries[$score] ?? "Conversation recorded. Review transcript for details.";
}
