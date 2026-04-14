<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/LeadService.php';

Auth::check();

$method = $_SERVER['REQUEST_METHOD'];
$action = sanitize($_GET['action'] ?? '');

header('Content-Type: application/json');

switch ($method) {
    case 'GET':
        if ($action === 'metrics') {
            jsonResponse(['success' => true, 'data' => LeadService::getDashboardMetrics()]);
        }
        if ($action === 'activity') {
            jsonResponse(['success' => true, 'data' => LeadService::getCallActivity()]);
        }
        if ($action === 'get' && isset($_GET['id'])) {
            $lead = LeadService::getLead((int)$_GET['id']);
            jsonResponse($lead ? ['success' => true, 'data' => $lead] : ['success' => false, 'error' => 'Not found'], $lead ? 200 : 404);
        }

        // List leads
        $filters = [
            'status' => sanitize($_GET['status'] ?? ''),
            'city'   => sanitize($_GET['city'] ?? ''),
            'search' => sanitize($_GET['search'] ?? ''),
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 20)));
        jsonResponse(['success' => true, 'data' => LeadService::getLeads($filters, $page, $perPage)]);
        break;

    case 'POST':
        // Support both JSON body and form POST
        $jsonInput = json_decode(file_get_contents('php://input'), true) ?? [];
        $postData  = array_merge($_POST, $jsonInput);
        $csrf = sanitize($postData['csrf_token'] ?? '');
        if (!verifyCsrf($csrf)) jsonResponse(['success' => false, 'error' => 'Invalid request.'], 403);
        $_POST = array_merge($_POST, $jsonInput); // merge so LeadService can read fields

        if ($action === 'import_csv') {
            if (empty($_FILES['csv_file'])) {
                jsonResponse(['success' => false, 'error' => 'No file uploaded.'], 400);
            }
            $file = $_FILES['csv_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                jsonResponse(['success' => false, 'error' => 'Upload error.'], 400);
            }
            if (!in_array($file['type'], ['text/csv', 'application/vnd.ms-excel', 'text/plain'])) {
                jsonResponse(['success' => false, 'error' => 'Only CSV files are allowed.'], 400);
            }
            $dest = UPLOAD_DIR . 'import_' . time() . '_' . bin2hex(random_bytes(4)) . '.csv';
            move_uploaded_file($file['tmp_name'], $dest);
            jsonResponse(LeadService::importCsv($dest));
        }

        // Create lead
        $result = LeadService::createLead($postData);
        jsonResponse($result, $result['success'] ? 201 : 422);
        break;

    case 'PUT':
        $id   = (int)($_GET['id'] ?? 0);
        parse_str(file_get_contents('php://input'), $data);
        if (!$id) jsonResponse(['success' => false, 'error' => 'ID required.'], 400);
        jsonResponse(LeadService::updateLead($id, $data));
        break;

    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'error' => 'ID required.'], 400);
        $ok = LeadService::deleteLead($id);
        jsonResponse(['success' => $ok], $ok ? 200 : 404);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed.'], 405);
}
