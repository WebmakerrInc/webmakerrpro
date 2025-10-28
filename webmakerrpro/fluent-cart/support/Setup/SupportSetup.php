<?php

namespace FluentCart\Support\Setup;

use FluentCart\Support\Models\Inbox;
use FluentCart\Support\Services\NotificationService;

class SupportSetup
{
    public static function activate(): void
    {
        self::ensureDefaultInbox();
        NotificationService::getSettings();
    }

    public static function ensureDefaultInbox(): void
    {
        if (!Inbox::where('is_default', 1)->exists()) {
            self::createDefaultInbox();
        }
    }

    public static function createDefaultInbox(): Inbox
    {
        $email = get_option('admin_email');
        $title = get_bloginfo('name') . ' ' . __('Support', 'fluent-cart');

        $inbox = Inbox::create([
            'title'      => $title,
            'email'      => $email,
            'is_default' => 1,
            'settings'   => [
                'signature' => sprintf(__('Thanks, %s support team', 'fluent-cart'), get_bloginfo('name')),
            ],
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ]);

        return $inbox;
    }
}
