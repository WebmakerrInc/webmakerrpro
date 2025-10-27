<?php

namespace FluentCart\App\Modules\PaymentMethods\PaystackGateway;

use FluentCart\App\Modules\PaymentMethods\Core\BaseGatewaySettings;
use FluentCart\App\Helpers\Helper;
use FluentCart\Framework\Support\Arr;

class PaystackSettingsBase extends BaseGatewaySettings
{
    public $settings;
    public $methodHandler = 'fluent_cart_payment_settings_paystack';


    public static function getDefaults()
    {
        return [
            'is_active' => 'no',
            'public_key' => '',
            'secret_key' => '',
            'webhook_secret' => '',
            'payment_mode' => 'test',
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

    public function getPublicKey()
    {
        return $this->get('public_key');
    }

    public function getSecretKey()
    {
        return Helper::decryptKey($this->get('secret_key'));
    }

    public function getWebhookSecret()
    {
        return Helper::decryptKey($this->get('webhook_secret'));
    }
}
