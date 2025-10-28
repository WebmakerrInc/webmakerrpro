<?php
/**
 * Plugin Name: Fluent Suite
 * Description: Unified modular Fluent Suite plugin combining FluentCart structure with modular integrations.
 * Author: Fluent Suite Team
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Text Domain: fluent-suite
 */

defined('ABSPATH') || exit;

if (!defined('FLUENT_SUITE_PATH')) {
    define('FLUENT_SUITE_VERSION', '0.1.0');
    define('FLUENT_SUITE_PATH', plugin_dir_path(__FILE__));
    define('FLUENT_SUITE_URL', plugin_dir_url(__FILE__));
    define('FLUENT_SUITE_FILE', __FILE__);
}

$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

register_activation_hook(__FILE__, ['\\FluentSuite\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['\\FluentSuite\\Plugin', 'deactivate']);

add_action('plugins_loaded', static function () {
    \FluentSuite\Plugin::boot(FLUENT_SUITE_FILE);
});
