<?php

namespace FluentSupport\Database\Migrations;

class TagRelationsMigrator
{
    static $tableName = 'fs_tag_pivot';

    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . static::$tableName;

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `tag_id` BIGINT(20) UNSIGNED NOT NULL,
                `source_id` BIGINT(20) UNSIGNED NOT NULL,
                `source_type` VARCHAR(192) NOT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `idx_tag_id` (`tag_id`),
                INDEX `idx_source_id` (`source_id`),
                INDEX `idx_source_type` (`source_type`),
                INDEX `idx_tag_source` (`tag_id`, `source_id`)
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
            'idx_tag_id' => 'tag_id',
            'idx_source_id' => 'source_id',
            'idx_source_type' => 'source_type',
            'idx_tag_source' => 'tag_id,source_id',
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
