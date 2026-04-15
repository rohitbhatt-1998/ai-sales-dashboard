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

if (!$callSid) {
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

// Fallback to existing transcript from call log when TwiML <Gather> built it incrementally.
$log = DB::fetchOne('SELECT id, transcript FROM call_logs WHERE call_sid = ? ORDER BY id DESC LIMIT 1', [$callSid]);
if (!$transcript && $log) {
    $transcript = trim((string)$log['transcript']);
}

if (!$transcript) {
    http_response_code(200);
    exit;
}

// Try AI summary if configured
$apiKey = DB::getConfig('ai_api_key');
$provider = strtolower(DB::getConfig('ai_provider', 'mock'));
if ($apiKey) {
    $summary = generateAiSummary($transcript, $lead['name'], $apiKey, $provider);
}
if (!$summary) {
    $summary = generateMockSummary($transcript, $lead['name']);
}

// Update call log
if ($log) {
    DB::execute(
        'UPDATE call_logs SET transcript = ?, summary = ?, lead_score = ?, status = CASE WHEN status = "calling" THEN "connected" ELSE status END WHERE id = ?',
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
// AI Summary Generator (OpenRouter/Cohere)
// -------------------------------------------------------
function generateAiSummary(string $transcript, string $leadName, string $apiKey, string $provider = 'mock'): string {
    $prompt = "You are a sales AI assistant. Analyze this call transcript and provide a brief 2-sentence summary of the call outcome and next recommended action for lead '{$leadName}'.\n\nTranscript:\n{$transcript}";

    $endpoint = '';
    $headers = ['Content-Type: application/json'];
    $payload = [];

    if ($provider === 'cohere') {
        $endpoint = 'https://api.cohere.com/v2/chat';
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        $payload = [
            'model' => 'command-r-plus',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a sales call analysis assistant.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.4,
            'max_tokens' => 150,
        ];
    } else {
        // OpenRouter (OpenAI-compatible endpoint)
        $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        $headers[] = 'HTTP-Referer: ' . BASE_URL;
        $headers[] = 'X-Title: AI Sales Dashboard';
        $payload = [
            'model' => 'openai/gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a sales call analysis assistant.'],
                ['role' => 'user',   'content' => $prompt],
            ],
            'max_tokens' => 150,
            'temperature' => 0.5,
        ];
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode((string)$response, true);
    if ($provider === 'cohere') {
        return $data['message']['content'][0]['text'] ?? '';
    }
    return $data['choices'][0]['message']['content'] ?? '';
}
