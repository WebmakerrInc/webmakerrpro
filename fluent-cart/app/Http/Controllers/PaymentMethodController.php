<?php

namespace FluentCart\App\Http\Controllers;

use FluentCart\App\Modules\PaymentMethods\Core\GatewayManager;
use FluentCart\App\Modules\PaymentMethods\PayPalGateway\API\API;
use FluentCart\App\Modules\PaymentMethods\PayPalGateway\API\Webhook;
use FluentCart\App\Modules\PaymentMethods\PayPalGateway\PayPalSettingsBase;
use FluentCart\Framework\Http\Request\Request;
use FluentCart\Framework\Support\Arr;
use FluentCart\App\Hooks\Handlers\GlobalPaymentHandler;
use FluentCart\App\Models\Order;


class PaymentMethodController extends Controller
{
    public function index(Request $request, GlobalPaymentHandler $globalHandler)
    {
        try {
            $gateways = $globalHandler->getAll();
            $categorizedGateways = [
                'available' => [],
                'offline' => [],
                'is_pro_required' => [],
                'upcoming' => []
            ];

            foreach ($gateways as $gateway) {
                if ($gateway['route'] === 'offline_payment') {
                    $categorizedGateways['offline'][] = $gateway;
                } elseif (isset($gateway['requires_pro']) && $gateway['requires_pro']) {
                    $categorizedGateways['is_pro_required'][] = $gateway;
                } elseif ($gateway['upcoming']) {
                    $categorizedGateways['upcoming'][] = $gateway;
                } else {
                    $categorizedGateways['available'][] = $gateway;
                }
            }

            return [
                'gateways' => array_merge(
                    $categorizedGateways['available'],
                    $categorizedGateways['offline'],
                    $categorizedGateways['is_pro_required'],
                    $categorizedGateways['upcoming']
                )
            ];

        } catch (\Exception $error) {
            return $this->sendError([
                'message' => $error->getMessage()
            ], 423);
        }
    }

    public function store(Request $request, GlobalPaymentHandler  $globalHandler)
    {
        $data = $request->settings;
        $method = sanitize_text_field($request->method);
        if (GatewayManager::has($method)) {
            $methodInstance = GatewayManager::getInstance($method);
            wp_send_json(
                $methodInstance->updateSettings($data),
                200
            );
        } else {
            throw new \Exception(__('No valid payment method found!', 'fluent-cart'));
        }
    }

    public function getSettings(Request $request, GlobalPaymentHandler $globalHandler)
    {
        try {
            return $globalHandler->getSettings(sanitize_text_field($request->method));
        } catch (\Exception $error) {
            return $this->sendError([
                'message' => $error->getMessage()
            ], 423);
        }
    }

    public function connectInfo(Request $request, GlobalPaymentHandler $globalHandler)
    {
        if (GatewayManager::has($request->getSafe('method', 'sanitize_text_field'))) {
            $methodInstance = GatewayManager::getInstance($request->getSafe('method', 'sanitize_text_field'));
            if (method_exists($methodInstance, 'getConnectInfo')) {
                wp_send_json(
                    $methodInstance->getConnectInfo(),
                    200
                );
            }
        }
    }

    public function disconnect(Request $request, GlobalPaymentHandler $globalHandler)
    {
        return $globalHandler->disconnect(
            sanitize_text_field($request->method),
            sanitize_text_field($request->mode),
        );
    }

    public function setPayPalWebhook(Request $request)
    {
        $setupWebhook = (new Webhook())->registerWebhook($request->getSafe('mode', 'sanitize_text_field'));
        if (is_wp_error($setupWebhook)) {
            return $this->sendError([
                'message' => $setupWebhook->get_error_message()
            ], 423);
        }

        return $this->sendSuccess([
            'message' => __('Webhook setup successfully! Please reload the page.', 'fluent-cart')
        ]);
    }

    public function checkPayPalWebhook(Request $request)
    {
       return (new Webhook())->maybeSetWebhook($request->getSafe('mode', 'sanitize_text_field'));
    }
}
