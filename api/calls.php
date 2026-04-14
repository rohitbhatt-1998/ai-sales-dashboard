<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/CallService.php';
require_once __DIR__ . '/../includes/LeadService.php';

Auth::check();

$method = $_SERVER['REQUEST_METHOD'];
$action = sanitize($_GET['action'] ?? '');

header('Content-Type: application/json');

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $csrf  = sanitize($input['csrf_token'] ?? '');

    if (!verifyCsrf($csrf)) {
        jsonResponse(['success' => false, 'error' => 'Invalid request.'], 403);
    }

    switch ($action) {
        case 'single':
            $leadId = (int)($input['lead_id'] ?? 0);
            if (!$leadId) jsonResponse(['success' => false, 'error' => 'lead_id required.'], 400);
            $result = CallService::initiateCall($leadId);
            jsonResponse($result);
            break;

        case 'bulk':
            $leadIds = array_map('intval', $input['lead_ids'] ?? []);
            if (empty($leadIds)) jsonResponse(['success' => false, 'error' => 'No leads selected.'], 400);
            $added = CallService::addToQueue($leadIds);
            // Process first call immediately
            $first = CallService::processQueue();
            jsonResponse([
                'success' => true,
                'queued'  => $added,
                'message' => "{$added} leads added to call queue.",
                'first_call' => $first,
            ]);
            break;

        case 'process_queue':
            $result = CallService::processQueue();
            jsonResponse($result);
            break;

        case 'retry_failed':
            $retried = CallService::retryFailed();
            jsonResponse(['success' => true, 'retried' => $retried]);
            break;

        default:
            jsonResponse(['error' => 'Unknown action.'], 400);
    }

} elseif ($method === 'GET') {
    switch ($action) {
        case 'queue_stats':
            jsonResponse(['success' => true, 'data' => CallService::getQueueStats()]);
            break;

        case 'logs':
            $leadId  = (int)($_GET['lead_id'] ?? 0);
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $perPage = 20;

            $where  = ['1=1'];
            $params = [];

            if ($leadId) {
                $where[]  = 'cl.lead_id = ?';
                $params[] = $leadId;
            }
            if (!empty($_GET['status'])) {
                $where[]  = 'cl.status = ?';
                $params[] = sanitize($_GET['status']);
            }
            if (!empty($_GET['date'])) {
                $where[]  = 'DATE(cl.created_at) = ?';
                $params[] = sanitize($_GET['date']);
            }

            $whereStr = implode(' AND ', $where);
            $total    = DB::fetchOne("SELECT COUNT(*) as c FROM call_logs cl WHERE {$whereStr}", $params)['c'] ?? 0;
            $pg       = paginate($total, $perPage, $page);

            $logs = DB::fetchAll(
                "SELECT cl.*, l.name as lead_name, l.phone as lead_phone, l.city as lead_city, l.status as lead_status
                 FROM call_logs cl
                 JOIN leads l ON l.id = cl.lead_id
                 WHERE {$whereStr}
                 ORDER BY cl.created_at DESC
                 LIMIT ? OFFSET ?",
                array_merge($params, [$perPage, $pg['offset']])
            );

            jsonResponse(['success' => true, 'data' => ['logs' => $logs, 'pagination' => $pg]]);
            break;

        case 'log_detail':
            $id  = (int)($_GET['id'] ?? 0);
            $log = DB::fetchOne(
                'SELECT cl.*, l.name as lead_name, l.phone as lead_phone, l.city as lead_city, l.email as lead_email, l.company as lead_company
                 FROM call_logs cl JOIN leads l ON l.id = cl.lead_id
                 WHERE cl.id = ?',
                [$id]
            );
            jsonResponse($log ? ['success' => true, 'data' => $log] : ['success' => false, 'error' => 'Not found'], $log ? 200 : 404);
            break;

        default:
            jsonResponse(['error' => 'Unknown action.'], 400);
    }
} else {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}
