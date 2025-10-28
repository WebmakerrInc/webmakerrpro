<?php

namespace FluentCart\Database\Migrations;

class SupportTicketRepliesMigrator extends Migrator
{
    public static string $tableName = 'fluentcart_ticket_replies';

    public static function getSqlSchema(): string
    {
        $indexPrefix = static::getDbPrefix() . 'fct_sprp_';

        return <<<SQL
`id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
`ticket_id` BIGINT UNSIGNED NOT NULL,
`user_id` BIGINT UNSIGNED NULL,
`author_name` VARCHAR(191) NOT NULL,
`author_email` VARCHAR(191) NULL,
`message` LONGTEXT NOT NULL,
`is_internal` TINYINT(1) NOT NULL DEFAULT 0,
`source` VARCHAR(50) NULL,
`created_at` DATETIME NULL,
`updated_at` DATETIME NULL,
INDEX `{$indexPrefix}ticket` (`ticket_id`),
INDEX `{$indexPrefix}created_at` (`created_at`)
SQL;
    }
}
