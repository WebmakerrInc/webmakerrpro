<?php

namespace FluentSupport\Database\Migrations;

class PersonsMigrator
{
    static $tableName = 'fs_persons';

    public static function migrate()
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . static::$tableName;

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `first_name` VARCHAR(192) NULL,
                `last_name` VARCHAR(192) NULL,
                `email` VARCHAR(192) NULL,
                `title` VARCHAR(192) NULL,
                `avatar` VARCHAR(192) NULL,
                `person_type` VARCHAR(192) DEFAULT 'customer',
                `status` VARCHAR(192) DEFAULT 'active',
                `ip_address` VARCHAR(20) NULL,
                `last_ip_address` VARCHAR(20) NULL,
                `address_line_1` VARCHAR(192) NULL,
                `address_line_2` VARCHAR(192) NULL,
                `city` VARCHAR(192) NULL,
                `zip` VARCHAR(192) NULL,
                `state` VARCHAR(192) NULL,
                `country` VARCHAR(192) NULL,
                `note` LONGTEXT NULL,
                `hash` VARCHAR(192) NULL,
                `user_id` BIGINT(20) UNSIGNED NULL,
                `description` MEDIUMTEXT NULL,
                `remote_uid` BIGINT(20) UNSIGNED NULL,
                `last_response_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `idx_email` (`email`),
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_ip_address` (`ip_address`)
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

        // @todo: We will remove this on final release
        // This is only for beta users
        if (!in_array('title', $existing_columns)) {
            $query = 'ALTER TABLE `' . $table . '` ADD `title` VARCHAR(192) NULL AFTER `email`';
            $wpdb->query($query);
        }

        if (!in_array('description', $existing_columns)) {
            $query = 'ALTER TABLE `' . $table . '` ADD `description` MEDIUMTEXT NULL AFTER `user_id`';
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
            'idx_email' => 'email',
            'idx_user_id' => 'user_id',
            'idx_ip_address' => 'ip_address',
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
