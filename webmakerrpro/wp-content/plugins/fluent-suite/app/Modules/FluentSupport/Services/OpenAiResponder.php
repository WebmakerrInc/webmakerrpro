<?php

namespace FluentSuite\Modules\FluentSupport\Services;

class OpenAiResponder
{
    protected TicketService $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    public function maybeGenerateReply(array $ticket, string $message): ?string
    {
        $apiKey = trim((string) get_option('fluent_suite_openai_api_key', ''));
        if (!$apiKey) {
            return null;
        }

        $enabled = apply_filters('fluent_suite_support_enable_auto_reply', true, $ticket);
        if (!$enabled) {
            return null;
        }

        $payload = [
            'model'    => apply_filters('fluent_suite_openai_model', 'gpt-4o-mini', $ticket),
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => apply_filters('fluent_suite_openai_system_prompt', 'You are a helpful support assistant. Provide concise, actionable answers for WordPress plugin users.', $ticket)
                ],
                [
                    'role'    => 'user',
                    'content' => $this->buildPrompt($ticket, $message)
                ],
            ],
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 20,
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['choices'][0]['message']['content'])) {
            return null;
        }

        $reply = trim((string) $body['choices'][0]['message']['content']);

        return apply_filters('fluent_suite_support_auto_reply', $reply, $ticket, $message);
    }

    protected function buildPrompt(array $ticket, string $message): string
    {
        $subject = $ticket['subject'] ?? '';
        $existingReplies = $ticket['replies'] ?? [];
        $history = '';
        foreach ($existingReplies as $reply) {
            $history .= strtoupper((string) $reply['author_type']) . ': ' . wp_strip_all_tags((string) $reply['message']) . "\n";
        }

        return sprintf(
            "Ticket Subject: %s\nCustomer Message: %s\nConversation History:\n%s",
            $subject,
            wp_strip_all_tags($message),
            $history
        );
    }
}
