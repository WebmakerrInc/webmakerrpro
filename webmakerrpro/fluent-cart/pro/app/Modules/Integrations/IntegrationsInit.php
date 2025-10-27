<?php

namespace FluentCartPro\App\Modules\Integrations;

class IntegrationsInit
{
    public function register()
    {
        add_action('fluent_cart/init', function () {
            (new WebhookConnect())->register();
        });
    }

}
