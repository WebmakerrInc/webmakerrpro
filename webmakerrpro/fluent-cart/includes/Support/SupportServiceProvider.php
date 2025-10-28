<?php

namespace FluentCart\Support;

use FluentCart\Support\Admin\AdminMenu;
use FluentCart\Support\Admin\AdminActions;
use FluentCart\Support\Http\SupportRestRoutes;

class SupportServiceProvider
{
    /**
     * Boot the support module.
     */
    public function boot(): void
    {
        add_action('init', [$this, 'registerHooks']);
        add_action('rest_api_init', [SupportRestRoutes::class, 'register']);
    }

    public function registerHooks(): void
    {
        add_filter('fluent_cart/global_admin_menu_items', [$this, 'registerSupportMenu']);

        if (is_admin()) {
            (new AdminMenu())->register();
            AdminActions::register();
        }
    }

    public function registerSupportMenu($menuItems)
    {
        $supportLinks = [
            'support'           => [
                'label' => __('Support Tickets', 'fluent-cart'),
                'link'  => admin_url('admin.php?page=fluent-cart-support'),
            ],
            'support_inboxes'   => [
                'label' => __('Support Inboxes', 'fluent-cart'),
                'link'  => admin_url('admin.php?page=fluent-cart-support-inboxes'),
            ],
            'support_settings'  => [
                'label' => __('Support Settings', 'fluent-cart'),
                'link'  => admin_url('admin.php?page=fluent-cart-support-settings'),
            ],
        ];

        if (!isset($menuItems['more'])) {
            $menuItems['more'] = [
                'label'    => __('More', 'fluent-cart'),
                'link'     => '#',
                'children' => []
            ];
        }

        if (empty($menuItems['more']['children']) || !is_array($menuItems['more']['children'])) {
            $menuItems['more']['children'] = [];
        }

        $menuItems['more']['children'] = $supportLinks + $menuItems['more']['children'];

        return $menuItems;
    }
}
