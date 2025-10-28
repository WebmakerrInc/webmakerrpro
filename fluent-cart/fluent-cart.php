<?php

defined('ABSPATH') or die;

/*
Plugin Name: WebmakerrPro
Description: WebmakerrPro WordPress Plugin
Version: 1.2.4
Author: WebmakerrPro Team
Author URI: https://webmakerrpro.com
Plugin URI: https://webmakerrpro.com
License: GPLv2 or later
Text Domain: fluent-cart
Domain Path: /language
*/
 
if (!defined('FLUENTCART_PLUGIN_PATH')) {
    define('FLUENTCART_VERSION', '1.2.4');
    define('FLUENTCART_DB_VERSION', '1.0.30');
    define('FLUENTCART_PLUGIN_PATH', plugin_dir_path(__FILE__));
    define('FLUENTCART_URL', plugin_dir_url(__FILE__));
    define('FLUENTCART_PLUGIN_FILE_PATH', __FILE__);
    define('FLUENTCART_UPLOAD_DIR', 'fluent_cart');
    define('FLUENT_CART_DIR_FILE', __FILE__);
    define('FLUENTCART_MIN_PRO_VERSION', '1.2.4');
}

if (!defined('FLUENTCART_PRO_PLUGIN_VERSION')) {
    define('FLUENTCART_PRO_PLUGIN_VERSION', FLUENTCART_VERSION);
    define('FLUENTCART_PRO_PLUGIN_DIR', FLUENTCART_PLUGIN_PATH . 'pro/');
    define('FLUENTCART_PRO_PLUGIN_URL', FLUENTCART_URL . 'pro/');
    define('FLUENTCART_PRO_PLUGIN_FILE_PATH', FLUENTCART_PLUGIN_FILE_PATH);
    define('FLUENTCART_MIN_CORE_VERSION', FLUENTCART_VERSION);
}

if (!defined('FLUENT_CART_PRO_DEV_MODE')) {
    define('FLUENT_CART_PRO_DEV_MODE', 'no');
}

update_option('__fluent-cart-pro_sl_info', [
    'license_key'     => '1415b451be1a13c283ba771ea52d38bb',
    'status'          => 'valid',
    'variation_id'    => '',
    'variation_title' => 'Pro',
    'expires'         => '2099-12-31',
    'activation_hash' => md5('1415b451be1a13c283ba771ea52d38bb' . home_url())
], false);

add_filter('pre_http_request', function ($preempt, $args, $url) {
    if (strpos($url, 'webmakerrpro.com') !== false && strpos($url, 'fluent-cart=') !== false) {
        return [
            'body'     => json_encode([
                'status'          => 'valid',
                'license'         => 'valid',
                'site_active'     => 'yes',
                'expiration_date' => '2099-12-31',
                'variation_id'    => '',
                'variation_title' => 'Pro',
                'activation_hash' => md5('1415b451be1a13c283ba771ea52d38bb' . home_url())
            ]),
            'response' => ['code' => 200]
        ];
    }

    return $preempt;
}, 10, 3);

register_activation_hook(__FILE__, function () {
    update_option('fluent_cart_do_activation_redirect', true);
});

$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

$proAutoload = __DIR__ . '/pro/vendor/autoload.php';
if (file_exists($proAutoload)) {
    require_once $proAutoload;
}

$bootstrap = require __DIR__ . '/boot/app.php';
$proBootstrap = null;

$proBootstrapPath = __DIR__ . '/pro/boot/app.php';
if (file_exists($proBootstrapPath)) {
    $proBootstrap = require $proBootstrapPath;
}

$app = $bootstrap(__FILE__);

if ($proBootstrap) {
    $proBootstrap(__FILE__);
}

return $app;
