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
        if (is_admin()) {
            (new AdminMenu())->register();
            AdminActions::register();
        }
    }
}
