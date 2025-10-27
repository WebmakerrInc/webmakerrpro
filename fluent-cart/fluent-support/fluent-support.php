<?php defined('ABSPATH') or die;

// Bootstrap Fluent Support within the bundled Fluent Cart plugin.
if (!defined('FLUENT_SUPPORT_VERSION')) {
    define('FLUENT_SUPPORT_VERSION', '1.10.0');
}

if (!defined('FLUENT_SUPPORT_PRO_MIN_VERSION')) {
    define('FLUENT_SUPPORT_PRO_MIN_VERSION', '1.10.0');
}

if (!defined('FLUENT_SUPPORT_UPLOAD_DIR')) {
    define('FLUENT_SUPPORT_UPLOAD_DIR', 'fluent-support');
}

if (!defined('FLUENT_SUPPORT_PLUGIN_URL')) {
    define('FLUENT_SUPPORT_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('FLUENT_SUPPORT_PLUGIN_PATH')) {
    define('FLUENT_SUPPORT_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

$supportAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($supportAutoload)) {
    require_once $supportAutoload;
}

call_user_func(function ($bootstrap) {
    $bootstrap(__FILE__);
}, require __DIR__ . '/boot/app.php');

add_action('wp_insert_site', function ($new_site) {
    $cartPlugin = defined('FLUENTCART_PLUGIN_BASENAME') ? FLUENTCART_PLUGIN_BASENAME : (defined('FLUENTCART_PLUGIN_FILE_PATH') ? plugin_basename(FLUENTCART_PLUGIN_FILE_PATH) : null);

    if ($cartPlugin && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network($cartPlugin)) {
        switch_to_blog($new_site->blog_id);
        (new \FluentSupport\App\Hooks\Handlers\ActivationHandler)->handle(false);
        restore_current_blog();
    }
});
