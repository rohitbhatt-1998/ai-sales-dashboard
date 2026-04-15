<?php
/**
 * Twilio TwiML — AI call script returned to Twilio
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$leadId = (int)($_GET['lead_id'] ?? 0);
$step   = max(0, (int)($_GET['q'] ?? 0));
$lead   = DB::fetchOne('SELECT * FROM leads WHERE id = ?', [$leadId]);

header('Content-Type: text/xml');

$opening   = DB::getConfig('opening_script', 'Hello, this is an AI assistant calling.');
$closing   = DB::getConfig('closing_statement', 'Thank you for your time!');
$language  = DB::getConfig('language_style', 'english');
$questions = DB::getConfig('question_flow', '');

$opening = str_replace('{{lead_name}}', $lead['name'] ?? 'there', $opening);
$closing = str_replace('{{lead_name}}', $lead['name'] ?? 'there', $closing);

$voice = ($language === 'hinglish') ? 'Polly.Aditi' : 'Polly.Joanna';

$questionLines = array_filter(array_map('trim', explode("\n", $questions)));
$questionLines = array_values($questionLines);
$nextUrl = BASE_URL . '/api/twiml.php?lead_id=' . $leadId . '&q=' . ($step + 1);

// Persist previous Gather result into the transcript.
$callSid = sanitize($_POST['CallSid'] ?? '');
$speech  = sanitize($_POST['SpeechResult'] ?? '');
if ($callSid && $speech && !empty($questionLines)) {
    $previousIndex = max(0, $step - 1);
    $askedQuestion = $questionLines[$previousIndex] ?? 'Question';
    $append = "AI: {$askedQuestion}\nLead: {$speech}\n";

    $log = DB::fetchOne('SELECT id, transcript FROM call_logs WHERE call_sid = ? ORDER BY id DESC LIMIT 1', [$callSid]);
    if ($log) {
        $existing = trim((string)($log['transcript'] ?? ''));
        $fullTranscript = trim($existing . "\n" . $append);
        DB::execute(
            'UPDATE call_logs SET transcript = ?, status = "connected" WHERE id = ?',
            [$fullTranscript, $log['id']]
        );
    }
}
?>
<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <?php if (!$lead): ?>
    <Say voice="<?= $voice ?>">Sorry, we could not find your call details.</Say>
    <Hangup/>
    <?php return; endif; ?>

    <?php if ($step === 0): ?>
    <Say voice="<?= $voice ?>"><?= htmlspecialchars($opening) ?></Say>
    <Pause length="1"/>
    <?php endif; ?>

    <?php if (!empty($questionLines) && isset($questionLines[$step])): ?>
    <Gather input="speech" timeout="4" speechTimeout="auto"
            action="<?= htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') ?>"
            method="POST">
        <Say voice="<?= $voice ?>"><?= htmlspecialchars($questionLines[$step]) ?></Say>
    </Gather>
    <Redirect method="POST"><?= htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') ?></Redirect>
    <?php else: ?>
    <Say voice="<?= $voice ?>"><?= htmlspecialchars($closing) ?></Say>
    <Hangup/>
    <?php endif; ?>

</Response>
