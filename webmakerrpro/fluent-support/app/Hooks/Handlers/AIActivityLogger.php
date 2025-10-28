<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\AIActivityLogs;
use FluentSupport\App\Services\Helper;

/**
 * Class AIActivityLogger
 *
 * This class handles logging AI activity hooks for FluentSupport.
 */
class AIActivityLogger
{
    /**
     * Registers all action hooks related to AI activities.
     */
    public function init()
    {
        add_action('fluent_support/ai_response_success', function ($ticketID, $prompt, $usedTokens, $model) {
            $settings = Helper::getOption('_ai_activity_settings', []);

            if (isset($settings['disable_logs']) && $settings['disable_logs'] === 'yes') {
                return;
            }

            $logData = [
                'agent_id' => get_current_user_id(),
                'ticket_id' => $ticketID,
                'model_name' => $model,
                'tokens' => intval($usedTokens),
                'prompt' => sanitize_text_field($prompt),
            ];

            AIActivityLogs::create($logData);
        }, 20, 5);
    }

}
