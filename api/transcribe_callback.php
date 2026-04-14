<?php
/**
 * Twilio Transcription Callback
 * Receives transcription data and updates call log + AI summary
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$leadId      = (int)($_GET['lead_id'] ?? 0);
$callSid     = sanitize($_POST['CallSid'] ?? '');
$transcript  = sanitize($_POST['TranscriptionText'] ?? '');

if (!$callSid || !$transcript) {
    http_response_code(200);
    exit;
}

$lead = DB::fetchOne('SELECT * FROM leads WHERE id = ?', [$leadId]);
if (!$lead) {
    http_response_code(200);
    exit;
}

// Classify lead score
$score   = classifyLeadScore($transcript);
$summary = '';

// Try OpenAI summary if configured
$apiKey = DB::getConfig('ai_api_key');
if ($apiKey) {
    $summary = generateAiSummary($transcript, $lead['name'], $apiKey);
}
if (!$summary) {
    $summary = generateMockSummary($transcript, $lead['name']);
}

// Update call log
$log = DB::fetchOne('SELECT id FROM call_logs WHERE call_sid = ? ORDER BY id DESC LIMIT 1', [$callSid]);
if ($log) {
    DB::execute(
        'UPDATE call_logs SET transcript = ?, summary = ?, lead_score = ? WHERE id = ?',
        [$transcript, $summary, $score, $log['id']]
    );
}

// Update lead score and status
DB::execute(
    'UPDATE leads SET status = ?, score = ? WHERE id = ?',
    [$score, match($score) { 'hot' => 90, 'warm' => 60, 'cold' => 20 }, $leadId]
);

http_response_code(200);
exit;

// -------------------------------------------------------
// OpenAI Summary Generator
// -------------------------------------------------------
function generateAiSummary(string $transcript, string $leadName, string $apiKey): string {
    $prompt = "You are a sales AI assistant. Analyze this call transcript and provide a brief 2-sentence summary of the call outcome and next recommended action for lead '{$leadName}'.\n\nTranscript:\n{$transcript}";

    $payload = json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a sales call analysis assistant.'],
            ['role' => 'user',   'content' => $prompt],
        ],
        'max_tokens' => 150,
        'temperature' => 0.5,
    ]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? '';
}
