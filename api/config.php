<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireAdmin();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

$allowedKeys = [
    'language_style', 'tone', 'opening_script', 'question_flow',
    'closing_statement', 'max_retries', 'call_delay_seconds',
    'ai_provider', 'ai_api_key', 'twilio_account_sid',
    'twilio_auth_token', 'twilio_phone_number'
];

if ($method === 'GET') {
    $config = DB::fetchAll('SELECT config_key, config_value FROM ai_config ORDER BY id');
    $map = [];
    foreach ($config as $row) {
        // Mask sensitive keys
        if (in_array($row['config_key'], ['ai_api_key', 'twilio_auth_token'])) {
            $val = $row['config_value'] ? '••••••••' . substr($row['config_value'], -4) : '';
        } else {
            $val = $row['config_value'];
        }
        $map[$row['config_key']] = $val;
    }
    jsonResponse(['success' => true, 'data' => $map]);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $csrf  = sanitize($input['csrf_token'] ?? '');
    if (!verifyCsrf($csrf)) jsonResponse(['success' => false, 'error' => 'Invalid request.'], 403);

    $updated = 0;
    foreach ($allowedKeys as $key) {
        if (isset($input[$key])) {
            // Don't overwrite masked values
            if (in_array($key, ['ai_api_key', 'twilio_auth_token']) && strpos($input[$key], '••••') !== false) {
                continue;
            }
            DB::setConfig($key, sanitize($input[$key]));
            $updated++;
        }
    }

    jsonResponse(['success' => true, 'updated' => $updated, 'message' => 'Configuration saved.']);

} else {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}
