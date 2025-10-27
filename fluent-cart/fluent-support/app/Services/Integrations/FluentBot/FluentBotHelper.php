<?php
namespace FluentSupport\App\Services\Integrations\FluentBot;

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Integrations\FluentBot\FluentBotAPI;
use FluentSupport\Framework\Support\Arr;
use WP_Error;
class FluentBotHelper
{
    const BASE_URL = 'https://beta.fluentbot.ai/ai';

    const ENDPOINTS = [
        'default' => '/responses',
        'ticket_reply' => '/chat/fs-completion',
    ];

    public function generateStreamResponse($prompt, $ticket, $productId, $conversationId = null)
    {
        $prompt = apply_filters('fluent_support/generate_response', $prompt, $ticket);
        $payload = [
            'ticket_conversation' => $this->getTicketMessages($ticket),
            'source' => 'fluent_support',
            'prompt' => $prompt,
            'stream' => true,
            'conversation_id' => $conversationId ?? null,
        ];
        return $this->makeStreamAPICall($payload, $prompt, $ticket->id, 'ticket_reply', $productId);
    }

    public function modifyResponse($prompt, $selectedText, $ticketId)
    {
        $prompt = apply_filters('fluent_support/modify_selected_text', $prompt);
        $payload = [
            'message' => "Instruction: {$prompt} Now apply this to the given text: {$selectedText}",
        ];

        return $this->makeAPICall($payload, $prompt, $ticketId);
    }

    public function generateTicketSummary($ticket)
    {
        $prompt = 'Provide a summary of the ticket from the customer\'s perspective. Each step should start with "-". Break it down into concise steps, with a maximum of 6 steps. Each step should be within 6 words per line. Use full stops for separation.';
        $prompt = apply_filters('fluent_support/generate_ticket_summary', $prompt);

        $messages = $this->getSimpleTicketMessages($ticket);
        $payload = [
            'message' => "Instruction: {$prompt} Ticket Data: " . json_encode($messages),
        ];

        return $this->makeAPICall($payload, $prompt, $ticket->id);
    }

    public function generateTicketTone($ticket)
    {
        $prompt = 'What is the tone of this ticket? Is it positive, negative, or neutral? Provide a response with a single word.';
        $prompt = apply_filters('fluent_support/find_customer_sentiment', $prompt);

        $messages = $this->getSimpleTicketMessages($ticket);
        $payload = [
            'message' => "Instruction: {$prompt} Ticket Data: " . json_encode($messages),
        ];

        return $this->makeAPICall($payload, $prompt, $ticket->id);
    }

    public function getPresetPrompts(string $type): array
    {
        if ($type === 'modifyResponse') {
            return $this->getModifyResponsePresets();
        }

        if ($type === 'createResponse') {
            return $this->getCreateResponsePresets();
        }

        return [];
    }

    private function makeAPICall(array $payload, string $prompt, int $ticketId, string $type = 'default', $productId = null )
    {
        $apiUrl = static::BASE_URL . static::ENDPOINTS[$type];

        $credentials = $this->resolveApiCredentials($productId);

        if (is_wp_error($credentials)) {
            return $credentials;
        }

        // Use bot_id instead of botId for the new API
        $payload['bot_id'] = $credentials['botId'];

        $api = new FluentBotAPI($credentials['apiKey'], $apiUrl);
        $result = $api->makeRequest($ticketId, $prompt, $payload);

        // For ticket_reply endpoint, return the full result with conversation_id
        // For other endpoints, return just the content for backward compatibility
        if ($type === 'ticket_reply' && is_array($result) && isset($result['content'])) {
            return $result;
        } elseif (is_array($result) && isset($result['content'])) {
            return $result['content'];
        }

        return $result;
    }

    private function makeStreamAPICall(array $payload, string $prompt, int $ticketId, string $type = 'default', $productId = null)
    {
        $apiUrl = static::BASE_URL . static::ENDPOINTS[$type];

        $credentials = $this->resolveApiCredentials($productId);

        if (is_wp_error($credentials)) {
            echo "data: " . json_encode(['error' => $credentials->get_error_message()]) . "\n\n";
            return;
        }

        // Use bot_id instead of botId for the new API
        $payload['bot_id'] = $credentials['botId'];

        $api = new FluentBotAPI($credentials['apiKey'], $apiUrl);
        $api->makeStreamRequest($ticketId, $prompt, $payload);
    }

    private function resolveApiCredentials($productId)
    {
        $meta = Meta::where([
            'object_type' => 'fluent_bot_settings',
            'object_id'   => 1,
            'key'         => '_fs_fluent_bot_config'
        ])->first();

        $config = $meta ? unserialize($meta->value) : [];

        $apiKey = $config['generalApiKey'] ?? '';
        $botId = $config['generalBotId'] ?? '';

        if ($productId && !empty($config['productMappings']) && is_array($config['productMappings'])) {
            foreach ($config['productMappings'] as $mapping) {
                if ((int)$mapping['productId'] === (int)$productId) {
                    $apiKey = $mapping['apiKey'] ?? $apiKey;
                    $botId = $mapping['botId'] ?? $botId;
                    break;
                }
            }
        }

        if (!$botId) {
            return new \WP_Error(
                'missing_bot_credentials',
                __('Bot ID is not set for this product.', 'fluent-support')
            );
        }

        return [
            'apiKey' => $apiKey,
            'botId'  => $botId
        ];
    }

    private function getTicketMessages($ticket): array
    {
        $messages = [];
        $ticketArray = $ticket->toArray();

        if (!empty($ticketArray['content'])) {
            $messages[] = [
                'role' => 'customer',
                'message' => $this->cleanText($ticketArray['content']),
            ];
        }

        foreach (Arr::get($ticketArray, 'responses', []) as $response) {
            $role = Arr::get($response, 'person.person_type') === 'customer' ? 'customer' : 'support_agent';
            $messages[] = [
                'role' => $role,
                'message' => $this->cleanText(Arr::get($response, 'content', '')),
            ];
        }

        return $messages;
    }

    private function getSimpleTicketMessages($ticket): array
    {
        $messages = [];
        $ticketArray = $ticket->toArray();

        if (!empty($ticketArray['content'])) {
            $messages[] = [
                'role' => 'human',
                'message' => $this->cleanText($ticketArray['content']),
            ];
        }

        foreach (Arr::get($ticketArray, 'responses', []) as $response) {
            $role = Arr::get($response, 'person.person_type') === 'customer' ? 'human' : 'ai';
            $messages[] = [
                'role' => $role,
                'message' => $this->cleanText(Arr::get($response, 'content', '')),
            ];
        }

        return $messages;
    }



    private function cleanText(string $text): string
    {
        return trim(strip_tags($text));
    }

    private function getModifyResponsePresets(): array
    {
        $presets = [
            [
                'label' => 'Improve Writing',
                'text' => 'shorten',
                'description' => 'Use AI to refine the text by removing unnecessary words and making it more concise while retaining the original meaning and key information.'
            ],
            [
                'label' => 'Fix Spelling & Grammar',
                'text' => 'lengthen',
                'description' => 'Apply AI to correct any spelling and grammatical errors in the text, ensuring it is free of mistakes and reads professionally.'
            ],
            [
                'label' => 'Make Shorter',
                'text' => 'friendly',
                'description' => 'AI will modify the text to make it shorter and more casual, making it suitable for informal or friendly communication.'
            ],
            [
                'label' => 'Make Longer',
                'text' => 'professional',
                'description' => 'Enhance the text by adding more details and using refined language to make it more formal and detailed, appropriate for professional settings.'
            ],
            [
                'label' => 'Simplify Language',
                'text' => 'simplify',
                'description' => 'Utilize AI to simplify complex phrases and terminology, making the text easier to read and understand for a general audience.'
            ]
        ];

        return apply_filters('fluent_support/get_modify_response_preset_prompts', $presets);
    }

    private function getCreateResponsePresets(): array
    {
        $presets = [
            [
                'label' => 'Request More Information',
                'text' => 'requestInfo',
                'description' => 'Ask the customer to provide additional details or clarification about the issue they reported. This helps in gathering more information to resolve the issue effectively.'
            ],
            [
                'label' => 'Acknowledge Issue',
                'text' => 'acknowledgeIssue',
                'description' => 'Confirm receipt of the customer\'s issue and reassure them that it is being investigated. This demonstrates that their concern is being taken seriously.'
            ],
            [
                'label' => 'Provide Solution',
                'text' => 'provideSolution',
                'description' => 'Offer a comprehensive solution or resolution to the problem described by the customer. This should address their concerns and provide actionable steps to resolve the issue.'
            ],
            [
                'label' => 'Follow Up',
                'text' => 'followUp',
                'description' => 'Reach out to the customer after a solution has been provided to ensure that their issue has been resolved to their satisfaction. This helps in confirming the resolution and maintaining good customer relations.'
            ],
            [
                'label' => 'Close Ticket',
                'text' => 'closeTicket',
                'description' => 'Notify the customer that their ticket will be closed as the issue has been resolved. Ensure that all their concerns are addressed before closing the ticket.'
            ]
        ];

        return apply_filters('fluent_support/get_create_response_preset_prompts', $presets);
    }
}
