<?php

namespace FluentSupport\App\Services\Integrations;

class IntegrationInit
{
    public function init()
    {
        if(defined('FLUENTCRM') && class_exists('\FluentSupport\App\Services\Integrations\FluentCrm\FluentCRMWidgets')) {
            (new \FluentSupport\App\Services\Integrations\FluentCrm\FluentCRMWidgets())->boot();
        }

        if (defined('FLUENTFORM') && class_exists('\FluentSupport\App\Services\Integrations\FluentForm\FeedIntegration')) {
            new \FluentSupport\App\Services\Integrations\FluentForm\FeedIntegration();
        }

        if (defined('FLUENTCART_VERSION') && class_exists('\FluentSupport\App\Services\Integrations\FluentCart\FluentCart')) {
            (new \FluentSupport\App\Services\Integrations\FluentCart\FluentCart())->boot();
        }
    }

}
