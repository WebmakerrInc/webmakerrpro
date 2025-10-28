<?php

namespace FluentCart\Support\Http\Controllers;

use FluentCart\Support\Services\NotificationService;
use WP_REST_Request;

class SettingsController
{
    public function getNotifications()
    {
        return rest_ensure_response(NotificationService::getSettings());
    }

    public function saveNotifications(WP_REST_Request $request)
    {
        NotificationService::saveSettings($request->get_params());
        return rest_ensure_response(['saved' => true]);
    }

    public function getAi()
    {
        return rest_ensure_response([
            'api_key' => NotificationService::getAiKey(),
        ]);
    }

    public function saveAi(WP_REST_Request $request)
    {
        NotificationService::saveAiKey((string) $request->get_param('ai_api_key'));
        return rest_ensure_response(['saved' => true]);
    }
}
