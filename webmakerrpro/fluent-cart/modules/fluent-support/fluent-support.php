<?php defined('ABSPATH') or die;
/*
 * Bundled Module: Fluent Support
 * Description: The Ultimate Support Plugin For Your WordPress.
 * Version: 1.10.0
 * Author: WPManageNinja LLC
 * Author URI: https://wpmanageninja.com
 * Plugin URI: https://fluentsupport.com
 * License: GPLv2 or later
 * Text Domain: fluent-support
 * Domain Path: /language
 */

defined('FLUENT_SUPPORT_VERSION') or define('FLUENT_SUPPORT_VERSION', '1.10.0');
defined('FLUENT_SUPPORT_PRO_MIN_VERSION') or define('FLUENT_SUPPORT_PRO_MIN_VERSION', '1.10.0');
defined('FLUENT_SUPPORT_UPLOAD_DIR') or define('FLUENT_SUPPORT_UPLOAD_DIR', 'fluent-support');
defined('FLUENT_SUPPORT_PLUGIN_URL') or define('FLUENT_SUPPORT_PLUGIN_URL', plugin_dir_url(__FILE__));
defined('FLUENT_SUPPORT_PLUGIN_PATH') or define('FLUENT_SUPPORT_PLUGIN_PATH', plugin_dir_path(__FILE__));

require __DIR__ . '/vendor/autoload.php';

$supportBootFile = defined('FLUENT_SUPPORT_BOOT_FILE') ? FLUENT_SUPPORT_BOOT_FILE : __FILE__;

call_user_func(function ($bootstrap) use ($supportBootFile) {
    $bootstrap($supportBootFile);
}, require(__DIR__ . '/boot/app.php'));


add_action('wp_insert_site', function ($new_site) use ($supportBootFile) {
    $networkPlugin = plugin_basename($supportBootFile);

    if (is_plugin_active_for_network($networkPlugin)) {
        switch_to_blog($new_site->blog_id);
        (new \FluentSupport\App\Hooks\Handlers\ActivationHandler)->handle(false);
        restore_current_blog();
    }
});
