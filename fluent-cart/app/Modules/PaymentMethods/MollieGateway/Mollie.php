<?php

namespace FluentCart\App\Modules\PaymentMethods\MollieGateway;

use FluentCart\App\Modules\PaymentMethods\Core\AbstractPaymentGateway;
use FluentCart\App\Services\Payments\PaymentInstance;
use FluentCart\App\Vite;

class Mollie extends AbstractPaymentGateway
{
    public array $supportedFeatures = [
        'payment',
        'refund',
        'webhook'
    ];

    public function __construct()
    {
        parent::__construct(new MollieSettingsBase());
    }

    public function meta(): array
    {
        return [
            'title' => __('Mollie', 'fluent-cart'),
            'route' => 'mollie',
            'slug' => 'mollie',
            'description' => __('Pay securely with Mollie - Credit Card, PayPal, SEPA, and more', 'fluent-cart'),
            'logo' => Vite::getAssetUrl("images/payment-methods/mollie-logo.svg"),
            'icon' => Vite::getAssetUrl("images/payment-methods/mollie-logo.svg"),
            'brand_color' => '#5265e3',
            'status' => $this->settings->get('is_active') === 'yes',
            'upcoming' => true,
        ];
    }

    public function boot()
    {
        // init IPN related class/actions here
        add_filter('fluent_cart/payment_methods/mollie_settings', [$this, 'getSettings'], 10, 2);
    }

    public function makePaymentFromPaymentInstance(PaymentInstance $paymentInstance)
    {
        // todo: will implement later
        die();
    }

    public function refund($refundInfo, $order, $transaction)
    {
        // todo: will implement later
        die();
    }

    public function handleIPN()
    {
        $payload = json_decode(file_get_contents('php://input'), true); // will get from request after verification
        $paymentId = $payload['id'] ?? '';
        
        if (!$paymentId) {
            http_response_code(400);
            exit('Invalid webhook payload');
        }

        try {
            $payment = $this->getMolliePayment($paymentId);
            
            if (!$payment) {
                throw new \Exception('Payment not found');
            }

            $orderId = $payment['metadata']['order_id'] ?? '';
            $order = fluent_cart_get_order($orderId);

            if (!$order) {
                throw new \Exception('Order not found');
            }

            switch ($payment['status']) {
                case 'paid':
                    $this->handlePaymentSuccess($payment, $order);
                    break;
                case 'failed':
                case 'canceled':
                case 'expired':
                    $this->handlePaymentFailure($payment, $order);
                    break;
            }

            http_response_code(200);
            exit('OK');

        } catch (\Exception $e) {
            http_response_code(500);
            exit('Webhook processing failed');
        }
    }

    public function getOrderInfo(array $data)
    {
        $items = $this->getCheckoutItems();
        
        $subTotal = 0;
        foreach ($items as $item) {
            $subTotal += intval($item['quantity'] * $item['unit_price']);
        }

        $paymentArgs = [
            'amount' => $subTotal,
            'currency' => $this->storeSettings->get('currency'),
            'description' => sprintf(__('Payment for %s', 'fluent-cart'), $this->storeSettings->get('business_name')),
        ];

        wp_send_json([
            'status' => 'success',
            'payment_args' => $paymentArgs,
            'has_subscription' => false
        ], 200);
    }

    public function fields(): array
    {
        $webhook_url = site_url() . '?fct_payment_listener=1&method=mollie';
        $webhook_instructions = sprintf(
            '<div>
                <p><b>%1$s</b><code class="copyable-content">%2$s</code></p>
                <p>%3$s</p>
                <br>
                <h4>%4$s</h4>
                <br>
                <p>%5$s</p>
                <p>%6$s <a href="https://www.mollie.com/dashboard/developers/webhooks" target="_blank">%7$s</a></p>
                <p>%8$s <code class="copyable-content">%2$s</code></p>
                <p>%9$s</p>
            </div>',
            __('Webhook URL: ', 'fluent-cart'),                    // %1$s
            $webhook_url,                                          // %2$s (reused)
            __('You should configure your Mollie webhooks to get all updates of your payments remotely.', 'fluent-cart'), // %3$s
            __('How to configure?', 'fluent-cart'),                // %4$s
            __('In your Mollie Dashboard:', 'fluent-cart'),        // %5$s
            __('Go to Settings > Webhooks >', 'fluent-cart'),      // %6$s
            __('Add webhook', 'fluent-cart'),                      // %7$s
            __('Enter The Webhook URL: ', 'fluent-cart'),          // %8$s
            __('Select all payment status events', 'fluent-cart')  // %9$s
        );

        return array(
//            'notice' => [
//                'value' => $this->getStoreModeNotice(),
//                'label' => __('Store Mode notice', 'fluent-cart'),
//                'type' => 'notice'
//            ],
            'upcoming' => [
                'value' => $this->isUpcoming(),
                'label' => __('Payment method is upcoming!', 'fluent-cart'),
                'type' => 'upcoming'
            ],
            'payment_mode' => [
                'type' => 'tabs',
                'schema' => [
                    [
                        'type' => 'tab',
                        'label' => __('Test credentials', 'fluent-cart'),
                        'value' => 'test',
                        'schema' => [
                            'test_api_key' => array(
                                'value' => '',
                                'label' => __('Test API Key', 'fluent-cart'),
                                'type' => 'password',
                                'placeholder' => __('test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'fluent-cart'),
                                'dependency' => [
                                    'depends_on' => 'payment_mode',
                                    'operator' => '=',
                                    'value' => 'test'
                                ]
                            ),
                        ],
                    ],
                    [
                        'type' => 'tab',
                        'label' => __('Live credentials', 'fluent-cart'),
                        'value' => 'live',
                        'schema' => [
                            'live_api_key' => array(
                                'value' => '',
                                'label' => __('Live API Key', 'fluent-cart'),
                                'type' => 'password',
                                'placeholder' => __('live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'fluent-cart'),
                                'dependency' => [
                                    'depends_on' => 'payment_mode',
                                    'operator' => '=',
                                    'value' => 'live'
                                ]
                            ),
                        ]
                    ]
                ]
            ],
            'webhook_desc' => array(
                'value' => $webhook_instructions,
                'label' => __('Webhook URL', 'fluent-cart'),
                'type' => 'html_attr'
            ),
        );
    }


    public static function validateSettings($data): array
    {
        $apiKey = $data['api_key'] ?? '';

        if (empty($apiKey)) {
            return [
                'status' => 'failed',
                'message' => __('API Key is required', 'fluent-cart')
            ];
        }

        if (!str_starts_with($apiKey, 'test_') && !str_starts_with($apiKey, 'live_')) {
            return [
                'status' => 'failed',
                'message' => __('Invalid Mollie API Key format', 'fluent-cart')
            ];
        }

        try {
            $testResponse = static::testApiConnection($apiKey);
            
            return [
                'status' => 'success',
                'message' => __('Mollie settings validated successfully', 'fluent-cart')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }

    public function getEnqueueScriptSrc($hasSubscription = 'no'): array
    {
        return [
            [
                'handle' => 'fluent-cart-mollie-checkout',
                'src' => Vite::getEnqueuePath('public/payment-methods/mollie-checkout.js'),
            ]
        ];
    }

    public function getEnqueueStyleSrc(): array
    {
        return [
            [
                'handle' => 'fluent-cart-mollie-styles',
                'src' => Vite::getEnqueuePath('public/payment-methods/mollie.css'),
            ]
        ];
    }

    private function createMolliePayment($data)
    {
        $apiKey = $this->settings->get('api_key');
        
        $response = wp_remote_post('https://api.mollie.com/v2/payments', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (wp_remote_retrieve_response_code($response) !== 201) {
            $error = $data['detail'] ?? 'Unknown error';
            throw new \Exception($error);
        }

        return $data;
    }

    private function processMollieRefund($paymentId, $refundData)
    {
        $apiKey = $this->settings->get('api_key');
        
        $response = wp_remote_post("https://api.mollie.com/v2/payments/{$paymentId}/refunds", [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($refundData),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (wp_remote_retrieve_response_code($response) !== 201) {
            $error = $data['detail'] ?? 'Unknown error';
            throw new \Exception($error);
        }

        return $data;
    }

    private function getMolliePayment($paymentId)
    {
        $apiKey = $this->settings->get('api_key');
        
        $response = wp_remote_get("https://api.mollie.com/v2/payments/{$paymentId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    private static function testApiConnection($apiKey)
    {
        $response = wp_remote_get('https://api.mollie.com/v2/methods', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            throw new \Exception(__('Invalid API credentials', 'fluent-cart'));
        }

        return true;
    }

    public function webHookPaymentMethodName()
    {
        return $this->getMeta('route');
    }

    private function getWebhookUrl()
    {
        return site_url('?fluent_cart_payment_api_notify=mollie');
    }

    private function getReturnUrl($order)
    {
        return add_query_arg([
            'fluent_cart_payment' => 'mollie',
            'order_id' => $order->id,
            'payment_hash' => $order->payment_hash
        ], site_url());
    }

    private function handlePaymentSuccess($payment, $order)
    {
        $order->payment_status = 'paid';
        $order->status = 'processing';
        $order->vendor_charge_id = $payment['id'];
        $order->save();

        do_action('fluent_cart/payment_success', [
            'order' => $order,
            'payment_intent' => $payment
        ]);
    }

    private function handlePaymentFailure($payment, $order)
    {
        $order->payment_status = 'failed';
        $order->status = 'failed';
        $order->save();

        do_action('fluent_cart/payment_failed', [
            'order' => $order,
            'payment_intent' => $payment
        ]);
    }
}
