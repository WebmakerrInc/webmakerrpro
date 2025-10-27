<?php

namespace FluentCart\Database;

use FluentCart\App\CPT\FluentProducts;
use FluentCart\App\Models\Product;

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

use FluentCart\App\Models\Subscription;
use FluentCart\Database\Migrations\AttributeGroupsMigrator;
use FluentCart\Database\Migrations\AttributeObjectRelationsMigrator;
use FluentCart\Database\Migrations\AttributeTermsMigrator;
use FluentCart\Database\Migrations\CartMigrator;
use FluentCart\Database\Migrations\CustomersMigrator;
use FluentCart\Database\Migrations\MetaMigrator;
use FluentCart\Database\Migrations\Migrator;
use FluentCart\Database\Migrations\OrderMetaMigrator;
use FluentCart\Database\Migrations\OrdersMigrator;
use FluentCart\Database\Migrations\OrdersItemsMigrator;
use FluentCart\Database\Migrations\OrderTransactionsMigrator;
use FluentCart\Database\Migrations\ProductDetailsMigrator;
use FluentCart\Database\Migrations\ProductDownloadsMigrator;
use FluentCart\Database\Migrations\ProductMetaMigrator;
use FluentCart\Database\Migrations\ProductVariationMigrator;
use FluentCart\Database\Migrations\ScheduledActionsMigrator;
use FluentCart\Database\Migrations\ShippingClassesMigrator;
use FluentCart\Database\Migrations\SubscriptionMetaMigrator;
use FluentCart\Database\Migrations\SubscriptionsMigrator;
use FluentCart\Database\Migrations\TaxClassesMigrator;
use FluentCart\Database\Migrations\TaxRatesMigrator;
use FluentCart\Database\Migrations\OrderTaxRateMigrator;
use FluentCart\Database\Migrations\CouponsMigrator;
use FluentCart\Database\Migrations\CustomerAddressesMigrator;
use FluentCart\Database\Migrations\CustomerMetaMigrator;
use FluentCart\Database\Migrations\OrderAddressesMigrator;
use FluentCart\Database\Migrations\OrderDownloadPermissionsMigrator;
use FluentCart\Database\Migrations\OrderOperationsMigrator;
use FluentCart\Database\Migrations\AppliedCouponsMigrator;
use FluentCart\Database\Migrations\LabelMigrator;
use FluentCart\Database\Migrations\LabelRelationshipsMigrator;
use FluentCart\Database\Migrations\ActivityMigrator;
use FluentCart\Database\Migrations\WebhookLogger;
use FluentCart\Framework\Database\Schema;
use FluentCartPro\App\Modules\Licensing\Models\License;
use FluentCartPro\App\Modules\Licensing\Models\LicenseActivation;
use FluentCartPro\App\Modules\Licensing\Models\LicenseMeta;
use FluentCartPro\App\Modules\Licensing\Models\LicenseSite;
use FluentCart\Database\Migrations\ShippingZonesMigrator;
use FluentCart\Database\Migrations\ShippingMethodsMigrator;

class DBMigrator
{
    private static array $migrators = [
        MetaMigrator::class,
        AttributeGroupsMigrator::class,
        AttributeObjectRelationsMigrator::class,
        AttributeTermsMigrator::class,
        CartMigrator::class,
        CouponsMigrator::class,
        CustomerAddressesMigrator::class,
        CustomerMetaMigrator::class,
        CustomersMigrator::class,
        OrderAddressesMigrator::class,
        OrderDownloadPermissionsMigrator::class,
        OrderMetaMigrator::class,
        OrderOperationsMigrator::class,
        OrdersItemsMigrator::class,
        OrdersMigrator::class,
        OrderTaxRateMigrator::class,
        OrderTransactionsMigrator::class,
        ProductDetailsMigrator::class,
        ProductDownloadsMigrator::class,
        ProductMetaMigrator::class,
        SubscriptionMetaMigrator::class,
        SubscriptionsMigrator::class,
        TaxClassesMigrator::class,
        TaxRatesMigrator::class,
        ProductVariationMigrator::class,
        AppliedCouponsMigrator::class,
        LabelMigrator::class,
        LabelRelationshipsMigrator::class,
        ActivityMigrator::class,
        WebhookLogger::class,
        ShippingZonesMigrator::class,
        ShippingMethodsMigrator::class,
        ShippingClassesMigrator::class,
        ScheduledActionsMigrator::class
    ];

    public static function migrateUp($network_wide = false)
    {
        global $wpdb;
        if ($network_wide) {
            // Retrieve all site IDs from this network (WordPress >= 4.6 provides easy to use functions for that).
            if (function_exists('get_sites') && function_exists('get_current_network_id')) {
                $site_ids = get_sites(array('fields' => 'ids', 'network_id' => get_current_network_id()));
            } else {
                $site_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs WHERE site_id = $wpdb->siteid;");
            }
            // Install the plugin for all these sites.
            foreach ($site_ids as $site_id) {
                switch_to_blog($site_id);
                self::run_migrate();
                restore_current_blog();
            }
        } else {
            self::run_migrate();
        }
    }

    public static function run_migrate()
    {
        self::migrate();
        self::maybeMigrateDBChanges();
        update_option('_fluent_cart_db_version', FLUENTCART_DB_VERSION, 'no');
    }

    public static function migrate()
    {
        /**
         * @var $migrator Migrator
         */
        foreach (self::$migrators as $migrator) {
            $migrator::migrate();
        }
    }

    public static function maybeMigrateDBChanges()
    {

        /*
         * TODO We will remove this after final release
         */
        $currentDBVersion = get_option('_fluent_cart_db_version');

        if (!$currentDBVersion || version_compare($currentDBVersion, FLUENTCART_DB_VERSION, '<')) {

            update_option('_fluent_cart_db_version', FLUENTCART_DB_VERSION, 'no');

            // let's check the orders table sequence number
            global $wpdb;

            if (!Schema::hasColumn('tax_behavior', 'fct_orders')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_orders` ADD COLUMN `tax_behavior` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 => no_tax, 1 => exclusive, 2 => inclusive' AFTER `rate`");
            }

            if (!Schema::hasColumn('slug', 'fct_tax_classes')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_tax_classes` ADD COLUMN `slug` `slug` VARCHAR(100) NULL AFTER `title`");
            }

            $ordersTable = $wpdb->prefix . 'fct_orders';

            // check if scheduled_at is exist or not
            $isReceiptNumberMigrated = $wpdb->get_col($wpdb->prepare("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='receipt_number' AND TABLE_NAME=%s", $ordersTable));
            if (!$isReceiptNumberMigrated) {
                $wpdb->query("ALTER TABLE {$ordersTable} ADD COLUMN `receipt_number` BIGINT NULL AFTER `parent_id`");
            }

            /**
             * Changing fct_meta.key to fct_meta.meta_key
             */
            if (Schema::hasColumn('discount_total', 'fct_orders')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_orders` CHANGE `discount_total` `manual_discount_total` BIGINT NOT NULL DEFAULT '0';");
            }

            /**
             * Changing fct_meta.key to fct_meta.meta_key
             */
            if (Schema::hasColumn('key', 'fct_meta')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_meta` CHANGE `key` `meta_key` varchar(192);");
            }

            /**
             * Changing fct_meta.value to fct_meta.meta_value
             */
            if (Schema::hasColumn('value', 'fct_meta')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_meta` CHANGE `value` `meta_value` longtext;");
            }

            /**
             * Changing fct_order_meta.key to fct_order_meta.meta_key and fct_order_meta.value to fct_order_meta.meta_value
             */
            if (Schema::hasColumn('key', 'fct_order_meta')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_order_meta` CHANGE `key` `meta_key` varchar(192);");
            }
            /**
             * Changing fct_meta.key to fct_meta.meta_key and fct_meta.value to fct_meta.meta_value
             */
            if (Schema::hasColumn('value', 'fct_order_meta')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_order_meta` CHANGE `value` `meta_value` longtext;");
            }

            /**
             * adding ltv column to fct_customers table
             */
            if (!Schema::hasColumn('ltv', 'fct_customers')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_customers` ADD COLUMN `ltv` BIGINT NOT NULL DEFAULT '0' AFTER `purchase_count`");
            }

            /**
             *  adding states column to fct_shipping_methods table
             */
            if (!Schema::hasColumn('states', 'fct_shipping_methods')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_shipping_methods` ADD COLUMN `states` LONGTEXT NULL AFTER `is_enabled`");
            }

            /**
             *  modify states column to json in fct_shipping_methods table
             */
           if (Schema::hasColumn('states', 'fct_shipping_methods')) {
               $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_shipping_methods` MODIFY COLUMN `states` JSON NULL");
           }

            /**
             * Changing fct_shipping_zones.regions to fct_shipping_zones.region
             */
            if (Schema::hasColumn('regions', 'fct_shipping_zones')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_shipping_zones` CHANGE `regions` `region` VARCHAR(192) NOT NULL;");
            }

            /**
             * adding uuid column to fct_subscriptions table
             */

            if (!Schema::hasColumn('uuid', 'fct_subscriptions')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_subscriptions` ADD COLUMN `uuid` VARCHAR(100) NOT NULL AFTER `id`");
            }

            if (Schema::hasColumn('initial_amount', 'fct_subscriptions')) {
                $res = $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_subscriptions` CHANGE `initial_amount` `signup_fee` bigint UNSIGNED NOT NULL DEFAULT 0");
            }

            $subscriptions = fluentCart('db')->table('fct_subscriptions')->select('id')->where('uuid', '')->orWhereNull('uuid');
            if ($subscriptions && $subscriptions->count() > 0) {
                $subscriptions = fluentCart('db')->table('fct_subscriptions')->select('id')->get()->keyBy('id')->toArray();
                $uuids = [];
                foreach ($subscriptions as $id => $subscription) {
                    $uuids[] = [
                        'id'   => $id,
                        'uuid' => md5(time() . wp_generate_uuid4())
                    ];

                }

                (new Subscription())->batchUpdate($uuids);
            }

            if (!Schema::hasColumn('meta', 'fct_order_addresses')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_order_addresses` ADD COLUMN `meta` JSON DEFAULT NULL AFTER `country`");
            }

            if (!Schema::hasColumn('meta', 'fct_customer_addresses')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_customer_addresses` ADD COLUMN `meta` JSON DEFAULT NULL AFTER `country`");
            }

            if (!Schema::hasColumn('meta', 'fct_order_tax_rate')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_order_tax_rate` ADD COLUMN `meta` JSON");
            }

            if (!Schema::hasColumn('filed_at', 'fct_order_tax_rate')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_order_tax_rate` ADD COLUMN `filed_at` DATETIME NULL AFTER `meta`");
            }

            if (Schema::hasColumn('categories', 'fct_tax_classes') && !Schema::hasColumn('meta', 'fct_tax_classes')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_tax_classes` CHANGE `categories` `meta` JSON");
            }

            if (!Schema::hasColumn('meta', 'fct_tax_classes')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_tax_classes` ADD COLUMN `meta` JSON");
            }

            if (!Schema::hasColumn('description', 'fct_tax_classes')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_tax_classes` ADD COLUMN `description` LONGTEXT NULL AFTER `title`");
            }

            if (!Schema::hasColumn('group', 'fct_tax_rates')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_tax_rates` ADD COLUMN `group` VARCHAR(45) NULL AFTER `name`");
            }

            if (!Schema::hasColumn('meta', 'fct_shipping_methods')) {
                $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "fct_shipping_methods` ADD COLUMN `meta` JSON NULL AFTER `order`");

            }
        }
    }

    public static function migrateDown($network_wide = false)
    {
        /**
         * @var $migrator Migrator
         */
        foreach (self::$migrators as $migrator) {
            $migrator::dropTable();
        }

        Product::query()->where('post_type', '=', FluentProducts::CPT_NAME)->delete();

        //Migrate Down The Licenses
        if (class_exists(License::class)) {
            License::query()->truncate();
            LicenseActivation::query()->truncate();
            LicenseSite::query()->truncate();
            LicenseMeta::query()->truncate();
        }
    }

    public static function refresh($network_wide = false)
    {
        static::migrateDown($network_wide);
        static::migrateUp($network_wide);
    }
}
