<?php

namespace FluentCart\Support\Admin;

use FluentCart\Support\Services\TicketService;
use FluentCart\Support\Services\NotificationService;

class AdminActions
{
    public static function register(): void
    {
        add_action('admin_post_fluentcart_support_create_ticket', [static::class, 'createTicket']);
        add_action('admin_post_fluentcart_support_reply_ticket', [static::class, 'replyTicket']);
        add_action('admin_post_fluentcart_support_close_ticket', [static::class, 'closeTicket']);
        add_action('admin_post_fluentcart_support_save_inbox', [static::class, 'saveInbox']);
        add_action('admin_post_fluentcart_support_delete_inbox', [static::class, 'deleteInbox']);
        add_action('admin_post_fluentcart_support_save_notifications', [static::class, 'saveNotifications']);
        add_action('admin_post_fluentcart_support_save_ai', [static::class, 'saveAi']);
    }

    public static function createTicket(): void
    {
        self::verifyNonce('fluentcart_support_ticket');

        $service = new TicketService();
        $data = wp_unslash($_POST);

        try {
            $service->createTicket($data);
            self::redirectWithMessage('fluent-cart-support', __('Ticket created successfully.', 'fluent-cart'));
        } catch (\Exception $exception) {
            self::redirectWithMessage('fluent-cart-support', $exception->getMessage(), 'error');
        }
    }

    public static function replyTicket(): void
    {
        self::verifyNonce('fluentcart_support_ticket');

        $service = new TicketService();
        $data = wp_unslash($_POST);

        try {
            $service->addReply((int) $data['ticket_id'], $data);
            self::redirectWithMessage('fluent-cart-support', __('Reply added successfully.', 'fluent-cart'));
        } catch (\Exception $exception) {
            self::redirectWithMessage('fluent-cart-support', $exception->getMessage(), 'error');
        }
    }

    public static function closeTicket(): void
    {
        self::verifyNonce('fluentcart_support_ticket');

        $service = new TicketService();
        $data = wp_unslash($_POST);

        try {
            $service->closeTicket((int) $data['ticket_id']);
            self::redirectWithMessage('fluent-cart-support', __('Ticket closed successfully.', 'fluent-cart'));
        } catch (\Exception $exception) {
            self::redirectWithMessage('fluent-cart-support', $exception->getMessage(), 'error');
        }
    }

    public static function saveInbox(): void
    {
        self::verifyNonce('fluentcart_support_inbox');

        $service = new TicketService();
        $data = wp_unslash($_POST);

        try {
            $service->saveInbox($data);
            self::redirectWithMessage('fluent-cart-support-inboxes', __('Inbox saved successfully.', 'fluent-cart'));
        } catch (\Exception $exception) {
            self::redirectWithMessage('fluent-cart-support-inboxes', $exception->getMessage(), 'error');
        }
    }

    public static function deleteInbox(): void
    {
        self::verifyNonce('fluentcart_support_inbox');

        $service = new TicketService();
        $data = wp_unslash($_POST);

        try {
            $service->deleteInbox((int) $data['inbox_id']);
            self::redirectWithMessage('fluent-cart-support-inboxes', __('Inbox deleted successfully.', 'fluent-cart'));
        } catch (\Exception $exception) {
            self::redirectWithMessage('fluent-cart-support-inboxes', $exception->getMessage(), 'error');
        }
    }

    public static function saveNotifications(): void
    {
        self::verifyNonce('fluentcart_support_notifications');

        $data = wp_unslash($_POST);
        NotificationService::saveSettings($data);
        self::redirectWithMessage('fluent-cart-support-settings', __('Notification settings saved.', 'fluent-cart'));
    }

    public static function saveAi(): void
    {
        self::verifyNonce('fluentcart_support_ai');

        $data = wp_unslash($_POST);
        NotificationService::saveAiKey($data['ai_api_key'] ?? '');
        self::redirectWithMessage('fluent-cart-support-settings', __('AI settings saved.', 'fluent-cart'));
    }

    protected static function verifyNonce(string $action): void
    {
        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), $action)) {
            wp_die(__('Sorry, your nonce did not verify.', 'fluent-cart'));
        }
    }

    protected static function redirectWithMessage(string $page, string $message, string $type = 'success'): void
    {
        $url = add_query_arg(
            [
                'page'                               => $page,
                'fluentcart_support_notice'          => rawurlencode($message),
                'fluentcart_support_notice_type'     => $type,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
