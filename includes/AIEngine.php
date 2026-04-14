<?php
// ============================================================
// AI Engine — scoring, summary generation, conversation flow
// ============================================================
require_once __DIR__ . '/Database.php';

class AIEngine {

    // ----------------------------------------------------------
    // Load AI config from DB
    // ----------------------------------------------------------
    public static function getConfig(): array {
        $rows   = Database::run('SELECT key_name, value FROM ai_config')->fetchAll();
        $config = [];
        foreach ($rows as $row) {
            $config[$row['key_name']] = $row['value'];
        }
        return $config;
    }

    // ----------------------------------------------------------
    // Update config key(s)
    // ----------------------------------------------------------
    public static function saveConfig(array $data): void {
        foreach ($data as $key => $value) {
            Database::run(
                'INSERT INTO ai_config (key_name, value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE value = ?',
                [$key, $value, $value]
            );
        }
    }

    // ----------------------------------------------------------
    // Score a conversation transcript and classify the lead
    // Returns: ['score' => 0-100, 'sentiment' => '...', 'classification' => 'Hot|Warm|Cold']
    // ----------------------------------------------------------
    public static function scoreConversation(string $transcript): array {
        $config    = self::getConfig();
        $useOpenAI = !empty($config['openai_api_key']);

        if ($useOpenAI) {
            return self::scoreWithOpenAI($transcript, $config['openai_api_key']);
        }

        return self::scoreLocally($transcript);
    }

    // ----------------------------------------------------------
    // Generate a summary for call logs
    // ----------------------------------------------------------
    public static function generateSummary(string $transcript, string $leadName): string {
        $config    = self::getConfig();
        $useOpenAI = !empty($config['openai_api_key']);

        if ($useOpenAI) {
            return self::summarizeWithOpenAI($transcript, $leadName, $config['openai_api_key']);
        }

        return self::summarizeLocally($transcript, $leadName);
    }

    // ----------------------------------------------------------
    // Build opening message based on config
    // ----------------------------------------------------------
    public static function buildOpening(string $leadName): string {
        $config = self::getConfig();
        $script = $config['opening_script'] ?? 'Hello [Lead Name], how are you today?';
        return str_replace('[Lead Name]', $leadName, $script);
    }

    // ----------------------------------------------------------
    // Simulate a full call conversation (for demo / no Twilio)
    // ----------------------------------------------------------
    public static function simulateCall(array $lead): array {
        $config   = self::getConfig();
        $tone     = $config['tone'] ?? 'friendly';
        $lang     = $config['language_style'] ?? 'English';
        $name     = $lead['name'];

        // Build a realistic-looking simulated transcript
        $opening   = self::buildOpening($name);
        $responses = self::getSimulatedResponses($name, $tone, $lang);
        $closing   = $config['closing_statement'] ?? 'Thank you for your time!';

        $transcript = "AI: $opening\n\n";
        foreach ($responses as $exchange) {
            $transcript .= "AI: {$exchange['ai']}\n";
            $transcript .= "{$name}: {$exchange['lead']}\n\n";
        }
        $transcript .= "AI: $closing\n";

        $score  = self::scoreConversation($transcript);
        $summary = self::generateSummary($transcript, $name);

        return [
            'transcript'     => $transcript,
            'summary'        => $summary,
            'score'          => $score['score'],
            'sentiment'      => $score['sentiment'],
            'classification' => $score['classification'],
            'duration'       => rand(90, 480),
        ];
    }

    // ----------------------------------------------------------
    // PRIVATE HELPERS
    // ----------------------------------------------------------

    private static function scoreLocally(string $transcript): array {
        $transcript_lower = strtolower($transcript);

        $positiveWords = ['yes','interested','great','sure','absolutely','definitely',
                          'sounds good','tell me more','when','how much','let\'s do it',
                          'perfect','excellent','budget','ready','price'];
        $negativeWords = ['no','not interested','busy','call back','remove','stop',
                          'unsubscribe','do not call','later','maybe later','too expensive'];

        $positive = 0;
        $negative = 0;

        foreach ($positiveWords as $w) {
            $positive += substr_count($transcript_lower, $w);
        }
        foreach ($negativeWords as $w) {
            $negative += substr_count($transcript_lower, $w);
        }

        $total = $positive + $negative;
        if ($total === 0) {
            $score = 40;
        } else {
            $score = (int) round(($positive / $total) * 100);
        }

        $classification = 'Cold';
        if ($score >= 70) $classification = 'Hot';
        elseif ($score >= 40) $classification = 'Warm';

        $sentiment = 'neutral';
        if ($positive > $negative * 1.5) $sentiment = 'positive';
        elseif ($negative > $positive * 1.5) $sentiment = 'negative';

        return ['score' => $score, 'sentiment' => $sentiment, 'classification' => $classification];
    }

    private static function summarizeLocally(string $transcript, string $leadName): string {
        $lines = array_filter(explode("\n", $transcript));
        $count = count($lines);

        $interestedCheck = stripos($transcript, 'interested') !== false
                        || stripos($transcript, 'yes') !== false
                        || stripos($transcript, 'sure') !== false;

        $interestText = $interestedCheck
            ? "showed strong interest in the product"
            : "was not ready to commit at this time";

        return "Call with {$leadName} — {$count} exchanges. Lead {$interestText}. "
             . "Key discussion points covered product features, pricing, and implementation timeline. "
             . "Follow-up recommended via email with detailed proposal.";
    }

    private static function scoreWithOpenAI(string $transcript, string $apiKey): array {
        $prompt = "Analyze the following sales call transcript and return ONLY a JSON object with: "
                . "{\"score\": <0-100>, \"sentiment\": \"positive|neutral|negative\", "
                . "\"classification\": \"Hot|Warm|Cold\"}. "
                . "Transcript:\n$transcript";

        $result = self::callOpenAI($apiKey, $prompt, 'gpt-3.5-turbo', 150);

        if ($result) {
            $json = json_decode($result, true);
            if ($json && isset($json['score'])) {
                return [
                    'score'          => (int)  $json['score'],
                    'sentiment'      => $json['sentiment'] ?? 'neutral',
                    'classification' => $json['classification'] ?? 'Warm',
                ];
            }
        }

        return self::scoreLocally($transcript);
    }

    private static function summarizeWithOpenAI(string $transcript, string $leadName, string $apiKey): string {
        $prompt = "Summarize this sales call transcript for lead '{$leadName}' in 2-3 concise sentences, "
                . "highlighting interest level, key objections, and recommended next steps.\n\n$transcript";

        $result = self::callOpenAI($apiKey, $prompt, 'gpt-3.5-turbo', 200);
        return $result ?: self::summarizeLocally($transcript, $leadName);
    }

    private static function callOpenAI(string $apiKey, string $prompt, string $model = 'gpt-3.5-turbo', int $maxTokens = 200): ?string {
        $payload = json_encode([
            'model'      => $model,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $maxTokens,
            'temperature'=> 0.3,
        ]);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) return null;

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private static function getSimulatedResponses(string $name, string $tone, string $lang): array {
        $friendly = [
            [
                'ai'   => "I wanted to share some exciting news about our AI-powered sales solution that's helping businesses like yours grow 3x faster. Are you open to hearing more?",
                'lead' => "Sure, I have a few minutes. What's it about?",
            ],
            [
                'ai'   => "What are the biggest challenges you're facing in your sales process right now?",
                'lead' => "Mainly we struggle with lead follow-ups and tracking our pipeline properly.",
            ],
            [
                'ai'   => "That's exactly what our platform solves! We automate follow-ups and give you a real-time dashboard. Do you have a budget set for tools like this?",
                'lead' => "Yes, we're open to investing if the ROI makes sense.",
            ],
            [
                'ai'   => "Wonderful! How soon are you looking to implement a solution?",
                'lead' => "Within the next month ideally.",
            ],
        ];

        $formal = [
            [
                'ai'   => "I am reaching out to discuss how our enterprise solution could address your operational requirements. May I ask a few qualifying questions?",
                'lead' => "Certainly, please proceed.",
            ],
            [
                'ai'   => "What is your current process for managing your sales pipeline and customer engagement?",
                'lead' => "We use spreadsheets and occasional CRM updates, but it's not very efficient.",
            ],
            [
                'ai'   => "I see. Our platform provides automated workflows, detailed analytics, and integration with existing systems. What would be your evaluation criteria?",
                'lead' => "ROI, ease of implementation, and ongoing support are our priorities.",
            ],
            [
                'ai'   => "Those are precisely our strengths. Would it be appropriate to schedule a technical demonstration?",
                'lead' => "That sounds reasonable. Send me the details.",
            ],
        ];

        return $tone === 'formal' ? $formal : $friendly;
    }
}
