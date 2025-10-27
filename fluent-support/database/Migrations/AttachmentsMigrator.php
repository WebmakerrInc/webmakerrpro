<?php

namespace FluentSupport\Database\Migrations;

class AttachmentsMigrator
{
    static $tableName = 'fs_attachments';

    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . static::$tableName;

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `ticket_id` BIGINT(20) UNSIGNED NULL,
                `person_id` BIGINT(20) UNSIGNED NULL,
                `conversation_id` BIGINT(20) UNSIGNED NULL,
                `file_type` VARCHAR(100) NULL,
                `file_path` TEXT NULL,
                `full_url` TEXT NULL,
                `settings` TEXT NULL,
                `title` VARCHAR(192) NULL,
                `file_hash` VARCHAR(192) NULL,
                `driver` VARCHAR(100) DEFAULT 'local',
                `status` VARCHAR(100) NULL DEFAULT 'active',
                `file_size` VARCHAR(100) NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `idx_ticket_id` (`ticket_id`),
                INDEX `idx_person_id` (`person_id`),
                INDEX `idx_conversation_id` (`conversation_id`),
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
        global $wpdb;
        
        // @todo: We will remove this on final release
        // This is only for beta users
        $existing_columns = $wpdb->get_col("DESC {$table}", 0);
        if(!in_array('status', $existing_columns)) {
            $query = "ALTER TABLE {$table} ADD `status` VARCHAR(100) NULL DEFAULT 'active' AFTER `driver`";
            $wpdb->query($query);
        }
        static::addMissingIndexes($table);
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
            'idx_ticket_id' => 'ticket_id',
            'idx_person_id' => 'person_id',
            'idx_conversation_id' => 'conversation_id',
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
