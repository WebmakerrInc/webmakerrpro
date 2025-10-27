<?php

namespace FluentSupport\Database\Migrations;

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
class AIActivityLogsMigrator
{
    static $tableName = 'fs_ai_activity_logs';

    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . static::$tableName;

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `agent_id` BIGINT(20) NULL,
                `ticket_id` BIGINT(20) NULL,
                `model_name` VARCHAR(50) NULL,
                `tokens` MEDIUMTEXT NULL,
                `prompt` LONGTEXT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `idx_agent_id` (`agent_id`),
                INDEX `idx_ticket_id` (`ticket_id`),
                INDEX `idx_model_name` (`model_name`),
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
            'idx_agent_id' => 'agent_id',
            'idx_ticket_id' => 'ticket_id',
            'idx_model_name' => 'model_name',
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
