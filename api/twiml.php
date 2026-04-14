<?php
/**
 * Twilio TwiML — AI call script returned to Twilio
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$leadId = (int)($_GET['lead_id'] ?? 0);
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
?>
<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="<?= $voice ?>"><?= htmlspecialchars($opening) ?></Say>
    <Pause length="1"/>

    <?php foreach ($questionLines as $question): ?>
    <Say voice="<?= $voice ?>"><?= htmlspecialchars($question) ?></Say>
    <Record maxLength="30" playBeep="false" timeout="5"
            action="<?= BASE_URL ?>/api/record_callback.php?lead_id=<?= $leadId ?>"
            transcribe="true"
            transcribeCallback="<?= BASE_URL ?>/api/transcribe_callback.php?lead_id=<?= $leadId ?>"/>
    <?php endforeach; ?>

    <Say voice="<?= $voice ?>"><?= htmlspecialchars($closing) ?></Say>
    <Hangup/>
</Response>
