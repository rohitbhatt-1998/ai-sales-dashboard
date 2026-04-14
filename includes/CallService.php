<?php
/**
 * Call Service — Handles call initiation, queue management, and Twilio/AI integration
 */

class CallService {

    // -------------------------------------------------------
    // Add lead(s) to call queue
    // -------------------------------------------------------
    public static function addToQueue(array $leadIds): int {
        $added = 0;
        $pdo = DB::connect();

        foreach ($leadIds as $leadId) {
            $leadId = (int) $leadId;

            // Check lead exists and is callable (not already in active queue, not exceeded retries)
            $lead = DB::fetchOne('SELECT * FROM leads WHERE id = ?', [$leadId]);
            if (!$lead) continue;

            // Avoid duplicate queuing
            $existing = DB::fetchOne(
                'SELECT id FROM call_queue WHERE lead_id = ? AND status IN ("pending","processing")',
                [$leadId]
            );
            if ($existing) continue;

            // Check retry limit
            if ($lead['retry_count'] >= QUEUE_MAX_RETRIES && $lead['status'] === 'no_answer') continue;

            DB::insert(
                'INSERT INTO call_queue (lead_id, priority, status, attempt) VALUES (?, 5, "pending", ?)',
                [$leadId, $lead['retry_count'] + 1]
            );

            // Mark lead as queued
            DB::execute('UPDATE leads SET status = "calling" WHERE id = ?', [$leadId]);
            $added++;
        }

        return $added;
    }

    // -------------------------------------------------------
    // Process next item in queue (called by API/cron)
    // -------------------------------------------------------
    public static function processQueue(): array {
        $item = DB::fetchOne(
            'SELECT q.*, l.name, l.phone FROM call_queue q
             JOIN leads l ON l.id = q.lead_id
             WHERE q.status = "pending"
             ORDER BY q.priority ASC, q.created_at ASC
             LIMIT 1'
        );

        if (!$item) {
            return ['status' => 'idle', 'message' => 'Queue is empty'];
        }

        // Mark as processing
        DB::execute('UPDATE call_queue SET status = "processing" WHERE id = ?', [$item['id']]);

        // Initiate call
        $result = self::initiateCall($item['lead_id'], $item['attempt']);

        if ($result['success']) {
            DB::execute(
                'UPDATE call_queue SET status = "done", processed_at = NOW() WHERE id = ?',
                [$item['id']]
            );
        } else {
            DB::execute(
                'UPDATE call_queue SET status = "failed", processed_at = NOW() WHERE id = ?',
                [$item['id']]
            );
        }

        return $result;
    }

    // -------------------------------------------------------
    // Initiate a single call for a lead
    // -------------------------------------------------------
    public static function initiateCall(int $leadId, int $attempt = 1): array {
        $lead = DB::fetchOne('SELECT * FROM leads WHERE id = ?', [$leadId]);
        if (!$lead) return ['success' => false, 'error' => 'Lead not found'];

        $provider = DB::getConfig('ai_provider', 'mock');
        $callSid  = 'CALL_' . strtoupper(bin2hex(random_bytes(8)));

        // Create call log entry
        $logId = DB::insert(
            'INSERT INTO call_logs (lead_id, call_sid, status, attempt, started_at) VALUES (?, ?, "calling", ?, NOW())',
            [$leadId, $callSid, $attempt]
        );

        // Update lead
        DB::execute(
            'UPDATE leads SET status = "calling", last_called_at = NOW() WHERE id = ?',
            [$leadId]
        );

        // Try real Twilio call if configured
        $twilioSid   = DB::getConfig('twilio_account_sid');
        $twilioToken = DB::getConfig('twilio_auth_token');
        $twilioPhone = DB::getConfig('twilio_phone_number');

        if ($twilioSid && $twilioToken && $twilioPhone) {
            $result = self::makeTwilioCall($lead, $callSid, $twilioSid, $twilioToken, $twilioPhone);
        } else {
            // MOCK call simulation
            $result = self::mockCall($lead, $logId);
        }

        return array_merge($result, ['log_id' => $logId, 'call_sid' => $callSid]);
    }

    // -------------------------------------------------------
    // Real Twilio call
    // -------------------------------------------------------
    private static function makeTwilioCall(array $lead, string $callSid, string $sid, string $token, string $from): array {
        $to = formatPhone($lead['phone']);
        $twimlUrl = BASE_URL . '/api/twiml.php?lead_id=' . $lead['id'];

        $postData = http_build_query([
            'To'     => $to,
            'From'   => $from,
            'Url'    => $twimlUrl,
            'Method' => 'POST',
            'StatusCallback' => BASE_URL . '/api/call_callback.php',
        ]);

        $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Calls.json");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_USERPWD        => "{$sid}:{$token}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode === 201 && isset($data['sid'])) {
            DB::execute(
                'UPDATE call_logs SET call_sid = ?, status = "calling" WHERE id = ?',
                [$data['sid'], DB::fetchOne('SELECT id FROM call_logs WHERE call_sid = ?', [$callSid])['id'] ?? 0]
            );
            return ['success' => true, 'provider' => 'twilio', 'sid' => $data['sid']];
        }

        error_log('Twilio Error: ' . $response);
        return ['success' => false, 'error' => $data['message'] ?? 'Twilio call failed'];
    }

    // -------------------------------------------------------
    // Mock call (when no Twilio configured) — simulates a call
    // -------------------------------------------------------
    private static function mockCall(array $lead, int $logId): array {
        // Simulate random outcomes
        $outcomes = ['connected', 'no_answer', 'connected', 'connected', 'no_answer'];
        $outcome  = $outcomes[array_rand($outcomes)];

        $duration   = 0;
        $transcript = '';
        $summary    = '';
        $score      = null;

        if ($outcome === 'connected') {
            $duration = rand(45, 240);

            $aiConfig = [
                'tone'             => DB::getConfig('tone', 'friendly'),
                'language_style'   => DB::getConfig('language_style', 'english'),
                'opening_script'   => DB::getConfig('opening_script', ''),
                'question_flow'    => DB::getConfig('question_flow', ''),
                'closing_statement'=> DB::getConfig('closing_statement', ''),
            ];

            $transcript = self::generateMockTranscript($lead, $aiConfig);
            $score      = classifyLeadScore($transcript);
            $summary    = generateMockSummary($transcript, $lead['name']);
        }

        // Update call log
        DB::execute(
            'UPDATE call_logs SET status = ?, duration = ?, transcript = ?, summary = ?, lead_score = ?, ended_at = NOW()
             WHERE id = ?',
            [$outcome === 'connected' ? 'completed' : 'no_answer', $duration, $transcript, $summary, $score, $logId]
        );

        // Update lead status and score
        $newLeadStatus = $outcome === 'connected' ? ($score ?? 'called') : 'no_answer';
        $retryIncrement = $outcome === 'no_answer' ? 1 : 0;

        DB::execute(
            'UPDATE leads SET status = ?, score = ?, retry_count = retry_count + ?, last_called_at = NOW()
             WHERE id = ?',
            [$newLeadStatus, self::scoreToInt($score), $retryIncrement, $lead['id']]
        );

        return ['success' => true, 'provider' => 'mock', 'outcome' => $outcome, 'score' => $score];
    }

    // -------------------------------------------------------
    // Generate realistic mock transcript
    // -------------------------------------------------------
    private static function generateMockTranscript(array $lead, array $config): string {
        $name = $lead['name'];
        $outcomes = [
            "hot" => "AI: Hello! This is an AI assistant calling on behalf of our sales team. Am I speaking with {$name}?\nLead: Yes, this is {$name}.\nAI: Great! Are you currently looking for solutions to improve your business processes?\nLead: Yes, actually I am. We've been looking for something like this.\nAI: That's wonderful! What is your biggest challenge right now?\nLead: Managing our leads and automating follow-ups. How much does it cost? I'd love to see a demo.\nAI: Absolutely! I'll have our team schedule a demo for you at the earliest. When would be a good time?\nLead: Tomorrow afternoon works great. Please book it.\nAI: Perfect! Thank you {$name}. Our representative will follow up shortly.",
            "warm" => "AI: Hello! This is an AI assistant. Am I speaking with {$name}?\nLead: Yes, who's this?\nAI: I'm calling on behalf of our sales team regarding our AI solutions. Do you have a moment?\nLead: I'm a bit busy. What is this about?\nAI: It's about automating your sales process. Are you interested in learning more?\nLead: Maybe. Send me some information, I'll think about it and get back to you.\nAI: Of course! I'll have our team send the details. Thank you {$name}!",
            "cold" => "AI: Hello! Am I speaking with {$name}?\nLead: Yes, what do you want?\nAI: I'm calling about our AI sales solution.\nLead: Not interested. Please don't call again.\nAI: I understand. I'll remove you from our list. Thank you for your time.",
        ];

        $keys = array_keys($outcomes);
        $pick = $keys[array_rand($keys)];
        return $outcomes[$pick];
    }

    private static function scoreToInt(?string $score): int {
        return match($score) {
            'hot'  => 90,
            'warm' => 60,
            'cold' => 20,
            default => 0,
        };
    }

    // -------------------------------------------------------
    // Get queue stats
    // -------------------------------------------------------
    public static function getQueueStats(): array {
        $stats = DB::fetchOne(
            'SELECT
                COUNT(*) as total,
                SUM(status = "pending") as pending,
                SUM(status = "processing") as processing,
                SUM(status = "done") as done,
                SUM(status = "failed") as failed
             FROM call_queue'
        );
        return $stats ?? ['total' => 0, 'pending' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0];
    }

    // -------------------------------------------------------
    // Retry failed calls
    // -------------------------------------------------------
    public static function retryFailed(): int {
        $failed = DB::fetchAll(
            'SELECT DISTINCT lead_id FROM call_queue
             WHERE status = "failed"
             AND attempt < ?',
            [QUEUE_MAX_RETRIES + 1]
        );

        $retried = 0;
        foreach ($failed as $row) {
            $lead = DB::fetchOne('SELECT retry_count FROM leads WHERE id = ?', [$row['lead_id']]);
            if ($lead && $lead['retry_count'] < QUEUE_MAX_RETRIES) {
                DB::execute(
                    'UPDATE call_queue SET status = "pending", attempt = attempt + 1 WHERE lead_id = ? AND status = "failed"',
                    [$row['lead_id']]
                );
                DB::execute(
                    'UPDATE leads SET retry_count = retry_count + 1 WHERE id = ?',
                    [$row['lead_id']]
                );
                $retried++;
            }
        }
        return $retried;
    }
}
