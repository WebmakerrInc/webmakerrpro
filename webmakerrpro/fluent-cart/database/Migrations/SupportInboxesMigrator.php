<?php

namespace FluentCart\Database\Migrations;

class SupportInboxesMigrator extends Migrator
{
    public static string $tableName = 'fluentcart_inboxes';

    public static function getSqlSchema(): string
    {
        $indexPrefix = static::getDbPrefix() . 'fct_spin_';

        return <<<SQL
`id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
`title` VARCHAR(191) NOT NULL,
`email` VARCHAR(191) NOT NULL,
`is_default` TINYINT(1) NOT NULL DEFAULT 0,
`settings` LONGTEXT NULL,
`created_at` DATETIME NULL,
`updated_at` DATETIME NULL,
INDEX `{$indexPrefix}default` (`is_default`)
SQL;
    }
}
