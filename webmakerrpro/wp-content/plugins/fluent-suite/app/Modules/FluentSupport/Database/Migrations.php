<?php

namespace FluentSuite\Modules\FluentSupport\Database;

class Migrations
{
    public function migrate(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $ticketTable    = $wpdb->prefix . 'fluent_suite_tickets';
        $repliesTable   = $wpdb->prefix . 'fluent_suite_ticket_replies';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $ticketSql = "CREATE TABLE {$ticketTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_email VARCHAR(190) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY customer_email (customer_email),
            KEY status (status)
        ) {$charsetCollate};";

        dbDelta($ticketSql);

        $replySql = "CREATE TABLE {$repliesTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT(20) UNSIGNED NOT NULL,
            author VARCHAR(190) NOT NULL,
            author_type VARCHAR(50) NOT NULL,
            message LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id),
            KEY author_type (author_type)
        ) {$charsetCollate};";

        dbDelta($replySql);
    }
}
