<?php

namespace FluentSupport\Database\Migrations;

class DataMetrixMigrator
{
    static $tableName = 'fs_data_metrix';

    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . static::$tableName;

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `stat_date` DATE NOT NULL,
                `data_type` VARCHAR(100) DEFAULT 'agent_stat',
                `agent_id` BIGINT(20) UNSIGNED NULL,
                `replies` INT(11) UNSIGNED NULL DEFAULT 0,  /* replies count in that date */
                `active_tickets` INT(11) UNSIGNED NULL DEFAULT 0,  /* ticket counts without new and closed */
                `resolved_tickets` INT(11) UNSIGNED NULL DEFAULT 0, /* tickets that got closed today */
                `new_tickets` INT(11) UNSIGNED NULL DEFAULT 0, /* all new status ticket count */
                `unassigned_tickets` INT(11) UNSIGNED NULL DEFAULT 0, /* For Global use case only */
                `close_to_average` INT(11) UNSIGNED NULL DEFAULT 0, /* average close time of the tickets */
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `idx_stat_date` (`stat_date`),
                INDEX `idx_agent_id` (`agent_id`),
                INDEX `idx_data_type` (`data_type`)
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
            'idx_stat_date' => 'stat_date',
            'idx_agent_id' => 'agent_id',
            'idx_data_type' => 'data_type',
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
