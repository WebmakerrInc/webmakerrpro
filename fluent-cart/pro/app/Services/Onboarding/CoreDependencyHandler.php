<?php

namespace FluentCartPro\App\Services\Onboarding;

use FluentCartPro\App\Utils\Enqueuer\Vite;
use FluentCartPro\App\Services\Translations\Translations;

class CoreDependencyHandler
{
    public function register()
    {
        // add a link to an admin menu which will redirect to /portal
        add_action('admin_menu', function () {
            add_menu_page(
                __('WebmakerrPro', 'fluent-cart-pro'),
                __('WebmakerrPro', 'fluent-cart-pro'),
                'edit_posts',
                'webmakerrpro',
                [$this, 'showAdminPage'],
                $this->logo(),
                10
            );
        });

        add_action('wp_ajax_fluent_cart_pro_install_core_plugin', [$this, 'installCorePlugin']);
    }

    public function installCorePlugin()
    {
        if(!current_user_can('activate_plugins')) {
            wp_send_json(['message' => 'Sorry, you do not have permission to install plugin!'], 403);
        }

        //just temporary force to download from outside link
        $otherSource = 'https://wpcolorlab.s3.amazonaws.com/fluent-cart.zip';

        // verify nonce
        if (!wp_verify_nonce($_POST['_nonce'], 'fluent-cart-onboarding-nonce')) {
            wp_send_json(['message' => 'Invalid nonce'], 403);
        }

        if (defined('FLUENTCART_VERSION')) {
            wp_send_json(['message' => 'Already installed'], 200);
        }

        $result = true;

        if ($otherSource) {
            OutsideInstaller::backgroundInstallerDirect([
                'name'      => 'WebmakerrPro',
                'repo-slug' => 'fluent-cart',
                'file'      => 'fluent-cart.php'
            ], 'fluent-cart', $otherSource);
        } else {
            $result = $this->installPlugin('fluent-cart');
        }

        if (is_wp_error($result)) {
            wp_send_json(['message' => $result->get_error_message()], 403);
        }

        wp_send_json_success(
            [
                'message'  => 'Successfully installed ',
                'redirect_url' => self_admin_url('admin.php?page=webmakerrpro')
            ],
            200
        );
    }

    public function showAdminPage()
    {
        vite::enqueueScript('fluent-cart-pro-onboard', 'admin/onboarding/onboarding-app.js', ['jquery'], FLUENTCART_PRO_PLUGIN_VERSION, true);

        $text = __('Install Plugin', 'fluent-cart-pro');

        if (file_exists(WP_PLUGIN_DIR . '/fluent-cart/fluent-cart.php')) {
            $text = __('Activate Plugin', 'fluent-cart-pro');
        }

        wp_localize_script('fluent-cart-pro-onboard', 'fluentCartOnboardingAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            '_nonce'   => wp_create_nonce('fluent-cart-onboarding-nonce'),
            'logo'     => $this->getMenuIcon(),
            'install_fluent_cart_text' => $text,
            'translations' => Translations::getTranslations()
        ]);

        Vite::enqueueStyle('fluent-cart-pro-onboard', 'admin/onboarding/onboarding-app.scss', [], FLUENTCART_PRO_PLUGIN_VERSION);
        echo '<div id="fluent_cart_onboarding_app"></div>';
    }

    public function logo()
    {
        return 'dashicons-admin-generic';
    }


    private function getMenuIcon()
    {
        return '';
    }





    private function installPlugin($pluginSlug)
    {
        $plugin = [
            'name'      => $pluginSlug,
            'repo-slug' => $pluginSlug,
            'file'      => $pluginSlug . '.php',
        ];

        $UrlMaps = [
            'fluent-cart' => [
                'admin_url' => admin_url('admin.php?page=webmakerrpro'),
                'title'     => 'Go to WebmakerrPro Dashboard',
            ],
        ];
        if (!isset($UrlMaps[$pluginSlug]) || (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS)) {
            return new \WP_Error('invalid_plugin', __('Invalid plugin or file mods are disabled.', 'fluent-cart-pro'));
        }

        try {
            $this->backgroundInstaller($plugin);
        } catch (\Exception $exception) {
            return new \WP_Error('plugin_install_error', $exception->getMessage());
        }
    }

    private function backgroundInstaller($plugin_to_install)
    {
        if (!empty($plugin_to_install['repo-slug'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';

            WP_Filesystem();

            $skin = new \Automatic_Upgrader_Skin();
            $upgrader = new \WP_Upgrader($skin);
            $installed_plugins = array_keys(\get_plugins());
            $plugin_slug = $plugin_to_install['repo-slug'];
            $plugin_file = isset($plugin_to_install['file']) ? $plugin_to_install['file'] : $plugin_slug . '.php';
            $installed = false;
            $activate = false;
            // See if the plugin is installed already.
            if (isset($installed_plugins[$plugin_file])) {
                $installed = true;
                $activate = !is_plugin_active($installed_plugins[$plugin_file]);
            }

            // Install this thing!
            if (!$installed) {
                // Suppress feedback.
                ob_start();

                try {
                    $plugin_information = plugins_api(
                        'plugin_information',
                        array(
                            'slug'   => $plugin_slug,
                            'fields' => array(
                                'short_description' => false,
                                'sections'          => false,
                                'requires'          => false,
                                'rating'            => false,
                                'ratings'           => false,
                                'downloaded'        => false,
                                'last_updated'      => false,
                                'added'             => false,
                                'tags'              => false,
                                'homepage'          => false,
                                'donate_link'       => false,
                                'author_profile'    => false,
                                'author'            => false,
                            ),
                        )
                    );

                    if (is_wp_error($plugin_information)) {
                        throw new \Exception(wp_kses_post($plugin_information->get_error_message()));
                    }

                    $package = $plugin_information->download_link;
                    $download = $upgrader->download_package($package);

                    if (is_wp_error($download)) {
                        throw new \Exception(wp_kses_post($download->get_error_message()));
                    }

                    $working_dir = $upgrader->unpack_package($download, true);

                    if (is_wp_error($working_dir)) {
                        throw new \Exception(wp_kses_post($working_dir->get_error_message()));
                    }

                    $result = $upgrader->install_package(
                        array(
                            'source'                      => $working_dir,
                            'destination'                 => WP_PLUGIN_DIR,
                            'clear_destination'           => false,
                            'abort_if_destination_exists' => false,
                            'clear_working'               => true,
                            'hook_extra'                  => array(
                                'type'   => 'plugin',
                                'action' => 'install',
                            ),
                        )
                    );

                    if (is_wp_error($result)) {
                        throw new \Exception(wp_kses_post($result->get_error_message()));
                    }

                    $activate = true;

                } catch (\Exception $e) {
                    throw new \Exception(esc_html($e->getMessage()));
                }

                // Discard feedback.
                ob_end_clean();
            }

            wp_clean_plugins_cache();

            // Activate this thing.
            if ($activate) {
                try {
                    $result = activate_plugin($installed ? $installed_plugins[$plugin_file] : $plugin_slug . '/' . $plugin_file);

                    if (is_wp_error($result)) {
                        throw new \Exception(esc_html($result->get_error_message()));
                    }
                } catch (\Exception $e) {
                    throw new \Exception(esc_html($e->getMessage()));
                }
            }
        }
    }
}
