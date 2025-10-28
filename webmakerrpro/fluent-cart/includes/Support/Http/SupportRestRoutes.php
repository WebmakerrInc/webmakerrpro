<?php

namespace FluentCart\Support\Http;

use FluentCart\Support\Http\Controllers\TicketController;
use FluentCart\Support\Http\Controllers\InboxController;
use FluentCart\Support\Http\Controllers\SettingsController;
use FluentCart\Support\Http\Controllers\AiController;
use WP_REST_Server;

class SupportRestRoutes
{
    public static function register(): void
    {
        $ticketController = new TicketController();
        $inboxController = new InboxController();
        $settingsController = new SettingsController();
        $aiController = new AiController();

        register_rest_route('fluentcart/v1', '/support/tickets', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$ticketController, 'index'],
                'permission_callback' => [static::class, 'canManage'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$ticketController, 'store'],
                'permission_callback' => [static::class, 'canManage'],
            ],
        ]);

        register_rest_route('fluentcart/v1', '/support/tickets/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$ticketController, 'show'],
            'permission_callback' => [static::class, 'canManage'],
            'args'                => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);

        register_rest_route('fluentcart/v1', '/support/tickets/(?P<id>\d+)/reply', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$ticketController, 'reply'],
            'permission_callback' => [static::class, 'canManage'],
        ]);

        register_rest_route('fluentcart/v1', '/support/tickets/(?P<id>\d+)/close', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$ticketController, 'close'],
            'permission_callback' => [static::class, 'canManage'],
        ]);

        register_rest_route('fluentcart/v1', '/support/inboxes', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$inboxController, 'index'],
                'permission_callback' => [static::class, 'canManage'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$inboxController, 'store'],
                'permission_callback' => [static::class, 'canManage'],
            ],
        ]);

        register_rest_route('fluentcart/v1', '/support/inboxes/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$inboxController, 'update'],
                'permission_callback' => [static::class, 'canManage'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$inboxController, 'delete'],
                'permission_callback' => [static::class, 'canManage'],
            ],
        ]);

        register_rest_route('fluentcart/v1', '/support/settings/notifications', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$settingsController, 'getNotifications'],
                'permission_callback' => [static::class, 'canManage'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$settingsController, 'saveNotifications'],
                'permission_callback' => [static::class, 'canManage'],
            ],
        ]);

        register_rest_route('fluentcart/v1', '/support/settings/ai', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$settingsController, 'getAi'],
                'permission_callback' => [static::class, 'canManage'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$settingsController, 'saveAi'],
                'permission_callback' => [static::class, 'canManage'],
            ],
        ]);

        register_rest_route('fluentcart/v1', '/support/ai/suggest', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$aiController, 'suggest'],
            'permission_callback' => [static::class, 'canManage'],
        ]);
    }

    public static function canManage(): bool
    {
        return current_user_can('manage_options');
    }
}
