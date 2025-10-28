<?php

namespace FluentCart\Database\Migrations;

class SupportTicketsMigrator extends Migrator
{
    public static string $tableName = 'fluentcart_tickets';

    public static function getSqlSchema(): string
    {
        $indexPrefix = static::getDbPrefix() . 'fct_sptk_';

        return <<<SQL
`id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
`inbox_id` BIGINT UNSIGNED NOT NULL,
`subject` VARCHAR(191) NOT NULL,
`customer_name` VARCHAR(191) NULL,
`customer_email` VARCHAR(191) NOT NULL,
`status` VARCHAR(20) NOT NULL DEFAULT 'open',
`priority` VARCHAR(20) NOT NULL DEFAULT 'normal',
`created_by` BIGINT UNSIGNED NULL,
`assigned_to` BIGINT UNSIGNED NULL,
`created_at` DATETIME NULL,
`updated_at` DATETIME NULL,
`closed_at` DATETIME NULL,
`last_reply_at` DATETIME NULL,
`last_reply_by` BIGINT UNSIGNED NULL,
`meta` LONGTEXT NULL,
INDEX `{$indexPrefix}inbox` (`inbox_id`),
INDEX `{$indexPrefix}status` (`status`),
INDEX `{$indexPrefix}created_at` (`created_at`)
SQL;
    }
}
