<?php

namespace FluentCart\Support\Admin;

use FluentCart\Database\Migrations\SupportInboxesMigrator;
use FluentCart\Database\Migrations\SupportTicketRepliesMigrator;
use FluentCart\Database\Migrations\SupportTicketsMigrator;
use FluentCart\Framework\Database\Schema;
use FluentCart\Support\Models\Ticket;
use FluentCart\Support\Services\NotificationService;
use FluentCart\Support\Services\TicketService;

class AdminMenu
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_notices', [$this, 'maybeRenderNotices']);
    }

    public function addMenu(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_submenu_page(
            'fluent-cart',
            __('Support Tickets', 'fluent-cart'),
            __('Support', 'fluent-cart'),
            'manage_options',
            'fluent-cart-support',
            [$this, 'renderTicketsPage']
        );

        add_submenu_page(
            'fluent-cart',
            __('Support Inboxes', 'fluent-cart'),
            __('Support Inboxes', 'fluent-cart'),
            'manage_options',
            'fluent-cart-support-inboxes',
            [$this, 'renderInboxesPage']
        );

        add_submenu_page(
            'fluent-cart',
            __('Support Settings', 'fluent-cart'),
            __('Support Settings', 'fluent-cart'),
            'manage_options',
            'fluent-cart-support-settings',
            [$this, 'renderSettingsPage']
        );
    }

    public function renderTicketsPage(): void
    {
        if (!$this->ensureSupportTables()) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__(
                    'Support tables are missing and could not be created automatically. Please review your database permissions and re-activate FluentCart.',
                    'fluent-cart'
                )
            );

            return;
        }

        $tickets = Ticket::with(['inbox', 'replies'])->orderBy('created_at', 'desc')->limit(50)->get();
        $service = new TicketService();
        $inboxes = $service->getInboxOptions();
        include __DIR__ . '/Views/tickets.php';
    }

    public function renderInboxesPage(): void
    {
        $service = new TicketService();
        $inboxes = $service->getAllInboxes();
        include __DIR__ . '/Views/inboxes.php';
    }

    public function renderSettingsPage(): void
    {
        $notifications = NotificationService::getSettings();
        $aiKey = NotificationService::getAiKey();
        include __DIR__ . '/Views/settings.php';
    }

    public function maybeRenderNotices(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (empty($_GET['fluentcart_support_notice'])) {
            return;
        }

        $message = sanitize_text_field(wp_unslash($_GET['fluentcart_support_notice']));
        $type = !empty($_GET['fluentcart_support_notice_type']) ? sanitize_text_field(wp_unslash($_GET['fluentcart_support_notice_type'])) : 'success';

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }

    private function ensureSupportTables(): bool
    {
        $migrators = [
            SupportInboxesMigrator::class,
            SupportTicketsMigrator::class,
            SupportTicketRepliesMigrator::class,
        ];

        foreach ($migrators as $migrator) {
            if (!Schema::hasTable($migrator::$tableName)) {
                $migrator::migrate();
            }
        }

        return Schema::hasTable(SupportTicketsMigrator::$tableName);
    }
}
