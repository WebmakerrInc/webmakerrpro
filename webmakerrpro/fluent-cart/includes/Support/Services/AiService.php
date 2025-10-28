<?php

namespace FluentCart\Support\Services;

use FluentCart\Support\Models\Ticket;
use FluentCart\Support\SupportException;
use FluentCart\Framework\Support\Arr;

class AiService
{
    public function suggestReply(?Ticket $ticket, string $prompt = ''): string
    {
        $apiKey = NotificationService::getAiKey();
        if (!$apiKey) {
            throw new SupportException(__('OpenAI API key is not configured.', 'fluent-cart'));
        }

        $messages = [
            [
                'role'    => 'system',
                'content' => __('You are a helpful support agent for an e-commerce store. Provide concise, empathetic responses.', 'fluent-cart'),
            ],
        ];

        if ($ticket) {
            $messages[] = [
                'role'    => 'user',
                'content' => sprintf(__('Ticket subject: %s', 'fluent-cart'), $ticket->subject),
            ];

            foreach ($ticket->replies as $reply) {
                $messages[] = [
                    'role'    => $reply->user_id ? 'assistant' : 'user',
                    'content' => sprintf('%s: %s', $reply->author_name, wp_strip_all_tags($reply->message)),
                ];
            }
        }

        if ($prompt) {
            $messages[] = [
                'role'    => 'user',
                'content' => wp_strip_all_tags($prompt),
            ];
        }

        $payload = [
            'model'       => 'gpt-3.5-turbo',
            'messages'    => $messages,
            'temperature' => 0.4,
            'max_tokens'  => 256,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            throw new SupportException($response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            $errorBody = wp_remote_retrieve_body($response);
            throw new SupportException(sprintf(__('OpenAI API error (%d): %s', 'fluent-cart'), $status, wp_strip_all_tags($errorBody)));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $suggestion = Arr::get($body, 'choices.0.message.content');

        if (!$suggestion) {
            throw new SupportException(__('No suggestion available from OpenAI.', 'fluent-cart'));
        }

        return trim((string) $suggestion);
    }
}
