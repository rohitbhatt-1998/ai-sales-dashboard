<?php
/**
 * Twilio Call Status Callback
 * Receives status updates from Twilio after a call completes
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

try {
    // Twilio sends POST
    $callSid     = sanitize($_POST['CallSid'] ?? '');
    $callStatus  = sanitize($_POST['CallStatus'] ?? '');
    $duration    = (int)($_POST['CallDuration'] ?? 0);

    if (!$callSid) {
        http_response_code(200);
        echo '<?xml version="1.0"?><Response></Response>';
        exit;
    }

// Map Twilio status to our status
$statusMap = [
    'completed'   => 'completed',
    'no-answer'   => 'no_answer',
    'busy'        => 'no_answer',
    'failed'      => 'failed',
    'canceled'    => 'failed',
    'in-progress' => 'connected',
    'queued'      => 'calling',
    'initiated'   => 'calling',
    'ringing'     => 'calling',
];

    $ourStatus = $statusMap[$callStatus] ?? 'failed';

    // Find the call log
    $log = DB::fetchOne('SELECT * FROM call_logs WHERE call_sid = ? ORDER BY id DESC LIMIT 1', [$callSid]);
    if ($log) {
        DB::execute(
            'UPDATE call_logs SET status = ?, duration = ?, ended_at = CASE WHEN ? = "completed" OR ? = "failed" OR ? = "no_answer" THEN NOW() ELSE ended_at END WHERE id = ?',
            [$ourStatus, $duration, $ourStatus, $ourStatus, $ourStatus, $log['id']]
        );

        // Update lead status
        $lead = DB::fetchOne('SELECT * FROM leads WHERE id = ?', [$log['lead_id']]);
        if ($lead) {
            if ($ourStatus === 'completed') {
                $leadStatus = in_array($lead['status'], ['hot', 'warm', 'cold'], true) ? $lead['status'] : 'called';
            } elseif ($ourStatus === 'connected') {
                $leadStatus = 'connected';
            } elseif ($ourStatus === 'no_answer') {
                $leadStatus = 'no_answer';
            } elseif ($ourStatus === 'calling') {
                $leadStatus = 'calling';
            } else {
                $leadStatus = $lead['status'];
            }

            DB::execute(
                'UPDATE leads SET status = ?, retry_count = retry_count + ?, last_called_at = NOW() WHERE id = ?',
                [$leadStatus, $ourStatus === 'no_answer' ? 1 : 0, $log['lead_id']]
            );
        }
    }

    http_response_code(200);
    echo '<?xml version="1.0"?><Response></Response>';
} catch (Throwable $e) {
    error_log('call_callback error: ' . $e->getMessage());
    // Never return 500 to Twilio callback retries.
    http_response_code(200);
    echo '<?xml version="1.0"?><Response></Response>';
}
