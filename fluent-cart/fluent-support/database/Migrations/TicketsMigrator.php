<?php

namespace FluentSupport\Database\Migrations;

class TicketsMigrator
{
    static $tableName = 'fs_tickets';

    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . static::$tableName;

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `customer_id` BIGINT(20) UNSIGNED NULL,
                `agent_id` BIGINT(20) UNSIGNED NULL,
                `mailbox_id` BIGINT(20) UNSIGNED NULL,
                `product_id` BIGINT(20) UNSIGNED NULL,
                `product_source` VARCHAR(192) NULL,
                `privacy` VARCHAR(100) DEFAULT 'private',
                `priority` VARCHAR(100) DEFAULT 'normal',
                `client_priority` VARCHAR(100) DEFAULT 'normal',
                `status` VARCHAR(100) DEFAULT 'new',
                `title` VARCHAR(192) NULL,
                `slug` VARCHAR(192) NULL,
                `hash` VARCHAR(192) NULL,
                `content_hash` VARCHAR(192) NULL,
                `message_id` VARCHAR(192) NULL,
                `source` VARCHAR(192) NULL,
                `content` LONGTEXT NULL,
                `secret_content` LONGTEXT NULL,
                `last_agent_response` TIMESTAMP NULL,
                `last_customer_response` TIMESTAMP NULL,
                `waiting_since` TIMESTAMP NULL,
                `response_count` INT(11) DEFAULT 0,
                `first_response_time` INT(11) NULL, /* Seconds took for first contact */
                `total_close_time` INT(11) NULL, /* Seconds took for closing this ticket */
                `resolved_at` TIMESTAMP NULL,
                `closed_by` BIGINT(20) UNSIGNED NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `idx_customer_id` (`customer_id`),
                INDEX `idx_agent_id` (`agent_id`),
                INDEX `idx_mailbox_id` (`mailbox_id`),
                INDEX `idx_product_id` (`product_id`),
                INDEX `idx_priority` (`priority`),
                INDEX `idx_status` (`status`),
                INDEX `idx_created_at` (`created_at`)
            ) $charsetCollate;";
            $created = dbDelta($sql);
            return $created;
        } else {
            static::alterTable($table);
        }

        return false;
    }

    public static function alterTable($table) 
    {
        static::addMissingColumns($table);
        static::addMissingIndexes($table);
    }

    public static function addMissingColumns($table)
    {
        global $wpdb;

        // Escape table name
        $table = esc_sql($table);

        // Get existing columns
        $existing_columns = $wpdb->get_col("DESC `$table`", 0);
        
        // Add waiting_since column if missing (beta user migration)
        if (!in_array('waiting_since', $existing_columns)) {
            $query = 'ALTER TABLE `' . $table . '` ADD `waiting_since` TIMESTAMP NULL AFTER `last_customer_response`';
            $wpdb->query($query);
        }
    }

    public static function addMissingIndexes($table)
    {
        global $wpdb;

        // Escape table name
        $table = esc_sql($table);

        // Get existing indexes
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `$table`");
        $existing_index_names = [];

        foreach ($existing_indexes as $index) {
            $existing_index_names[] = $index->Key_name;
        }

        // Desired indexes
        $indexes = [
            'idx_customer_id' => 'customer_id',
            'idx_agent_id' => 'agent_id',
            'idx_mailbox_id' => 'mailbox_id',
            'idx_product_id' => 'product_id',
            'idx_priority' => 'priority',
            'idx_status' => 'status',
            'idx_created_at' => 'created_at',
        ];

        // Add missing indexes
        foreach ($indexes as $index_name => $column_name) {
            if (!in_array($index_name, $existing_index_names)) {
                $sql = "ALTER TABLE `$table` ADD INDEX `$index_name` (`$column_name`)";
                $wpdb->query($sql);
            }
        }
    }
}
