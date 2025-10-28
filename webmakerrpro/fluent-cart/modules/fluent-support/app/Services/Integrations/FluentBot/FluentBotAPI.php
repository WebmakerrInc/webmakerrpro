<?php

namespace FluentSupport\App\Services\Integrations\FluentBot;

use WP_Error;

class FluentBotAPI
{
    protected $apiKey;
    protected $apiUrl;

    public function __construct(?string $apiKey, string $apiUrl)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = $apiUrl;
    }

    public function makeRequest(int $ticketId, $prompt, array $args = [])
    {
        $response = $this->sendRequest($args);

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            $code = $response->get_error_code();
            return new \WP_Error($code, $message);
        }

        $responseBody = json_decode(wp_remote_retrieve_body($response), true) ?? [];

        if (!$responseBody || !is_array($responseBody)) {
            return new \WP_Error('fluent_bot_error', __('Invalid or empty response from API', 'fluent-support'));
        }

        if (!empty($responseBody['error'])) {
            $message = $responseBody['error']['message'] ?? __('Unknown error occurred', 'fluent-support');
            return new \WP_Error('fluent_bot_error', $message);
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode !== 200) {
            $error = $responseBody['message'] ?? __('Something went wrong.', 'fluent-support');
            return new \WP_Error($statusCode, __($error, 'fluent-support'));
        }

        $content = $responseBody['response'] ?? '';

        if (empty($content)) {
            return new \WP_Error('fluent_bot_error', __('No AI response found in the API response.', 'fluent-support'));
        }

        $totalTokens = $responseBody['token_usage']['total_tokens'] ?? $responseBody['totalTokens'] ?? 0;
        do_action('fluent_support/ai_response_success', $ticketId, $prompt, $totalTokens, "Fluent Bot");

        // Return both content and conversation_id if available
        return [
            'content' => $content,
            'conversation_id' => $responseBody['conversation_id'] ?? null
        ];
    }

    public function makeStreamRequest(int $ticketId, $prompt, array $args = [])
    {
        $timeout = apply_filters('fs_ai_request_timeout', 120);

        // Use cURL for streaming
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($args));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            !empty($this->apiKey) ? 'Authorization: Bearer ' . $this->apiKey : ''
        ]);

        $buffer = '';
        $conversationId = null;

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$buffer, &$conversationId) {
            $buffer .= $data;

            // Process complete SSE events from the AI API
            $events = explode("\n\n", $buffer);
            $buffer = array_pop($events); // Keep incomplete event in buffer

            foreach ($events as $event) {
                if (trim($event)) {
                    $lines = explode("\n", $event);
                    $eventType = '';
                    $eventId = '';
                    $eventDataLines = [];

                    foreach ($lines as $line) {
                        if (strpos($line, 'event: ') === 0) {
                            $eventType = trim(substr($line, 7));
                        } elseif (strpos($line, 'id: ') === 0) {
                            $eventId = trim(substr($line, 4));
                        } elseif (strpos($line, 'data: ') === 0) {
                            $eventDataLines[] = substr($line, 6);
                        }
                    }

                    // Forward the event to the browser with proper formatting
                    if ($eventType) {
                        echo "event: {$eventType}\n";

                        // Include ID if present
                        if ($eventId !== '') {
                            echo "id: {$eventId}\n";
                        }

                        // Handle multiple data lines properly
                        if (!empty($eventDataLines)) {
                            foreach ($eventDataLines as $dataLine) {
                                echo "data: {$dataLine}\n";
                            }
                        } else {
                            echo "data: \n";
                        }

                        echo "\n";

                        // Store conversation_id for later use
                        if ($eventType === 'conversation_id' && !empty($eventDataLines)) {
                            $conversationId = $eventDataLines[0];
                        }

                        flush();
                    }
                }
            }

            return strlen($data);
        });

        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 128); // Smaller buffer for faster streaming

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_error($ch)) {
            echo "event: error\n";
            echo "data: " . json_encode(['error' => curl_error($ch)]) . "\n\n";
            flush();
        }

        curl_close($ch);

        if ($httpCode === 200) {
            do_action('fluent_support/ai_response_success', $ticketId, $prompt, 0, "Fluent Bot");
        }
    }

    protected function sendRequest(array $payload)
    {
        $headers = [
            'Content-Type'  => 'application/json',
        ];
        // Add Authorization header only if API key is provided
        if (!empty($this->apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        $timeout = apply_filters('fs_ai_request_timeout', 60);

        return wp_remote_post($this->apiUrl, [
            'headers' => $headers,
            'body'    => wp_json_encode($payload),
            'timeout' => $timeout,
        ]);
    }
}
