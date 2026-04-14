<?php
// ============================================================
// Twilio Webhook — TwiML response for AI-guided calls
// ============================================================
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/CallModel.php';
require_once __DIR__ . '/../../includes/LeadModel.php';
require_once __DIR__ . '/../../includes/AIEngine.php';

header('Content-Type: text/xml');

$callId = (int)($_GET['call_id'] ?? 0);
$event  = $_GET['event'] ?? '';
$step   = (int)($_GET['step'] ?? 0);

// ---- Status callback ----
if ($event === 'status') {
    $callStatus = $_POST['CallStatus'] ?? '';
    $duration   = (int)($_POST['CallDuration'] ?? 0);
    $sid        = $_POST['CallSid'] ?? '';

    if ($callId) {
        $statusMap = [
            'completed' => 'Completed',
            'busy'      => 'Busy',
            'no-answer' => 'No Answer',
            'failed'    => 'Failed',
            'canceled'  => 'Failed',
        ];
        $mapped = $statusMap[$callStatus] ?? null;
        if ($mapped) {
            CallModel::update($callId, [
                'status'   => $mapped,
                'duration' => $duration,
                'ended_at' => date('Y-m-d H:i:s'),
            ]);

            // Update lead
            $call = CallModel::find($callId);
            if ($call && $mapped === 'Completed') {
                LeadModel::update($call['lead_id'], [
                    'status'         => 'Called',
                    'call_count'     => $call['lead_id'],
                    'last_called_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
}

// ---- Main TwiML ----
$call = $callId ? CallModel::find($callId) : null;
$config = AIEngine::getConfig();
$lang   = ($config['language_style'] ?? 'English') === 'Hinglish' ? 'hi-IN' : 'en-US';
$voice  = 'Polly.Joanna';

if (!$call) {
    echo '<?xml version="1.0" encoding="UTF-8"?>
<Response>
  <Say voice="' . $voice . '" language="' . $lang . '">Sorry, we encountered an error. Goodbye.</Say>
  <Hangup/>
</Response>';
    exit;
}

$lead      = LeadModel::find($call['lead_id']);
$leadName  = $lead ? $lead['name'] : 'there';
$questions = array_filter(explode("\n", $config['question_flow'] ?? ''));
$questions = array_values($questions);

// Build TwiML based on call step
$webhookBase = rtrim(APP_URL, '/') . '/api/twilio/voice.php?call_id=' . $callId;
$nextStep    = $step + 1;

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<Response>' . "\n";

if ($step === 0) {
    // Opening
    $opening = htmlspecialchars(AIEngine::buildOpening($leadName));
    echo "  <Say voice=\"$voice\" language=\"$lang\">$opening</Say>\n";
    if (!empty($questions)) {
        echo "  <Gather input=\"speech\" timeout=\"10\" speechTimeout=\"auto\" action=\"{$webhookBase}&amp;step=1\">\n";
        $q1 = htmlspecialchars(strip_tags($questions[0]));
        echo "    <Say voice=\"$voice\" language=\"$lang\">$q1</Say>\n";
        echo "  </Gather>\n";
    } else {
        $closing = htmlspecialchars($config['closing_statement'] ?? 'Thank you. Goodbye!');
        echo "  <Say voice=\"$voice\" language=\"$lang\">$closing</Say>\n";
        echo "  <Hangup/>\n";
    }
} elseif (isset($questions[$step - 1])) {
    $qIndex = $step; // next question
    if (isset($questions[$qIndex])) {
        $q = htmlspecialchars(strip_tags($questions[$qIndex]));
        echo "  <Gather input=\"speech\" timeout=\"10\" speechTimeout=\"auto\" action=\"{$webhookBase}&amp;step={$nextStep}\">\n";
        echo "    <Say voice=\"$voice\" language=\"$lang\">$q</Say>\n";
        echo "  </Gather>\n";
    } else {
        // All questions done — closing
        $closing = htmlspecialchars($config['closing_statement'] ?? 'Thank you for your time. Goodbye!');
        echo "  <Say voice=\"$voice\" language=\"$lang\">$closing</Say>\n";
        echo "  <Hangup/>\n";
    }
} else {
    $closing = htmlspecialchars($config['closing_statement'] ?? 'Thank you. Goodbye!');
    echo "  <Say voice=\"$voice\" language=\"$lang\">$closing</Say>\n";
    echo "  <Hangup/>\n";
}

echo '</Response>';
echo ob_get_clean();
