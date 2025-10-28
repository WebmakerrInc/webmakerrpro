<?php

namespace FluentCart\Support\Services;

use FluentCart\Support\Models\Ticket;
use FluentCart\Support\Models\TicketReply;

class NotificationService
{
    protected const OPTION_KEY = 'fluentcart_support_notifications';
    protected const AI_KEY_OPTION = 'fluentcart_support_ai_key';

    public static function getSettings(): array
    {
        $defaults = [
            'notify_admin'      => true,
            'notify_inbox_email'=> true,
            'log_internal'      => true,
        ];

        $settings = get_option(self::OPTION_KEY, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        return wp_parse_args($settings, $defaults);
    }

    public static function saveSettings(array $data): void
    {
        $settings = [
            'notify_admin'       => !empty($data['notify_admin']),
            'notify_inbox_email' => !empty($data['notify_inbox_email']),
            'log_internal'       => !empty($data['log_internal']),
        ];

        update_option(self::OPTION_KEY, $settings, false);
    }

    public static function notifyNewTicket(Ticket $ticket): void
    {
        $settings = self::getSettings();
        $subject = sprintf(__('New support ticket: %s', 'fluent-cart'), $ticket->subject);
        $body = sprintf("%s\n\n%s", __('A new support ticket has been created.', 'fluent-cart'), self::formatTicketSummary($ticket));

        if (!empty($settings['notify_admin'])) {
            wp_mail(get_option('admin_email'), $subject, $body);
        }

        if (!empty($settings['notify_inbox_email']) && $ticket->inbox) {
            wp_mail($ticket->inbox->email, $subject, $body);
        }

        if (!empty($settings['log_internal'])) {
            self::logInternal($ticket, __('Notification dispatched for new ticket.', 'fluent-cart'));
        }
    }

    public static function notifyTicketReply(Ticket $ticket, TicketReply $reply): void
    {
        $settings = self::getSettings();
        $subject = sprintf(__('New reply on ticket #%d', 'fluent-cart'), $ticket->id);
        $body = sprintf("%s\n\n%s", __('A new reply has been added to a support ticket.', 'fluent-cart'), self::formatReplySummary($reply));

        if (!empty($settings['notify_admin'])) {
            wp_mail(get_option('admin_email'), $subject, $body);
        }

        if (!empty($settings['notify_inbox_email']) && $ticket->inbox) {
            wp_mail($ticket->inbox->email, $subject, $body);
        }

        if (!empty($settings['log_internal'])) {
            self::logInternal($ticket, __('Notification dispatched for ticket reply.', 'fluent-cart'));
        }
    }

    public static function logInternal(Ticket $ticket, string $message): void
    {
        $meta = $ticket->meta ?? [];
        if (!isset($meta['notifications'])) {
            $meta['notifications'] = [];
        }

        $meta['notifications'][] = [
            'message' => $message,
            'logged_at' => current_time('mysql', true),
        ];

        $ticket->meta = $meta;
        $ticket->save();
    }

    public static function getAiKey(): string
    {
        $key = get_option(self::AI_KEY_OPTION, '');
        return is_string($key) ? $key : '';
    }

    public static function saveAiKey(string $key): void
    {
        update_option(self::AI_KEY_OPTION, trim($key), false);
    }

    protected static function formatTicketSummary(Ticket $ticket): string
    {
        return sprintf(
            "%s: %s\n%s: %s\n%s: %s",
            __('Subject', 'fluent-cart'),
            $ticket->subject,
            __('Customer', 'fluent-cart'),
            $ticket->customer_name . ' <' . $ticket->customer_email . '>',
            __('Status', 'fluent-cart'),
            $ticket->status
        );
    }

    protected static function formatReplySummary(TicketReply $reply): string
    {
        return sprintf(
            "%s: %s\n%s:\n%s",
            __('Author', 'fluent-cart'),
            $reply->author_name,
            __('Message', 'fluent-cart'),
            wp_strip_all_tags($reply->message)
        );
    }
}
