<?php

namespace FluentCart\App\Modules\PaymentMethods\MollieGateway;

use FluentCart\App\Modules\PaymentMethods\Core\BaseGatewaySettings;
use FluentCart\App\Helpers\Helper;
use FluentCart\Framework\Support\Arr;

class MollieSettingsBase extends BaseGatewaySettings
{
    public $settings;
    public $methodHandler = 'fluent_cart_payment_settings_mollie';

    public static function getDefaults()
    {
        return [
            'is_active' => 'no',
            'api_key' => '',
            'payment_mode' => 'test',
            'webhook_url' => '',
        ];
    }

    public function isActive(): bool
    {
        return $this->settings['is_active'] == 'yes';
    }

    public function get($key = '')
    {
        if ($key && isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        return $this->settings;
    }

    public function getMode()
    {
        return $this->get('payment_mode');
    }

    public function getApiKey()
    {
        return Helper::decryptKey($this->get('api_key'));
    }
}
