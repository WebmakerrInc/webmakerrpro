<?php

namespace FluentCart\App\Modules\PaymentMethods\PaystackGateway;

use FluentCart\App\Modules\PaymentMethods\Core\AbstractPaymentGateway;
use FluentCart\App\Services\Payments\PaymentInstance;
use FluentCart\App\Vite;

class Paystack extends AbstractPaymentGateway
{
    public array $supportedFeatures = [
        'payment',
        'refund',
        'webhook'
    ];

    public function __construct()
    {
        parent::__construct(new PaystackSettingsBase());
    }

    public function meta(): array
    {
        return [
            'title' => __('Paystack', 'fluent-cart'),
            'route' => 'paystack',
            'slug' => 'paystack',
            'description' => __('Pay securely with Paystack - Cards, Bank Transfer, USSD, and Mobile Money', 'fluent-cart'),
            'logo' => Vite::getAssetUrl("images/payment-methods/paystack-logo.svg"),
            'icon' => Vite::getAssetUrl("images/payment-methods/paystack-logo.svg"),
            'brand_color' => '#0fa958',
            'status' => $this->settings->get('is_active') === 'yes',
            'upcoming' => true,
        ];
    }

    public function boot()
    {
        // init IPN related class/actions here
        add_filter('fluent_cart/payment_methods/paystack_settings', [$this, 'getSettings'], 10, 2);
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
        // Verify webhook signature
        if (!$this->verifyWebhookSignature($payload)) {
            http_response_code(401);
            exit('Unauthorized');
        }

        $event = $payload['event'] ?? '';
        
        switch ($event) {
            case 'charge.success':
                $this->handleChargeSuccess($payload);
                break;
            case 'charge.failed':
                $this->handleChargeFailed($payload);
                break;
            case 'refund.processed':
                $this->handleRefundProcessed($payload);
                break;
        }

        http_response_code(200);
        exit('OK');
    }

    public function getOrderInfo(array $data)
    {
        $items = $this->getCheckoutItems();
        
        $subTotal = 0;
        foreach ($items as $item) {
            $subTotal += intval($item['quantity'] * $item['unit_price']);
        }

        $paymentArgs = [
            'public_key' => $this->settings->getPublicKey(),
            'amount' => $subTotal * 100, // Convert to kobo
            'currency' => $this->storeSettings->get('currency'),
        ];

        wp_send_json([
            'status' => 'success',
            'payment_args' => $paymentArgs,
            'has_subscription' => false
        ], 200);
    }

    public function fields(): array
    {
        $webhook_url = site_url() . '?fct_payment_listener=1&method=paystack';
        $webhook_instructions = sprintf(
            '<div>
                <p><b>%1$s</b><code class="copyable-content">%2$s</code></p>
                <p>%3$s</p>
                <br>
                <h4>%4$s</h4>
                <br>
                <p>%5$s</p>
                <p>%6$s <a href="https://dashboard.paystack.com/#/settings/developer" target="_blank">%7$s</a></p>
                <p>%8$s <code class="copyable-content">%2$s</code></p>
                <p>%9$s</p>
            </div>',
            __('Webhook URL: ', 'fluent-cart'),                    // %1$s
            $webhook_url,                                          // %2$s (reused)
            __('You should configure your Paystack webhooks to get all updates of your payments remotely.', 'fluent-cart'), // %3$s
            __('How to configure?', 'fluent-cart'),                // %4$s
            __('In your Paystack Dashboard:', 'fluent-cart'),      // %5$s
            __('Go to Settings > Webhooks >', 'fluent-cart'),      // %6$s
            __('Add webhook', 'fluent-cart'),                      // %7$s
            __('Enter The Webhook URL: ', 'fluent-cart'),          // %8$s
            __('Select payment events', 'fluent-cart')             // %9$s
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
                            'test_public_key' => array(
                                'value' => '',
                                'label' => __('Test Public Key', 'fluent-cart'),
                                'type' => 'text',
                                'placeholder' => __('pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'fluent-cart'),
                                'dependency' => [
                                    'depends_on' => 'payment_mode',
                                    'operator' => '=',
                                    'value' => 'test'
                                ]
                            ),
                            'test_secret_key' => array(
                                'value' => '',
                                'label' => __('Test Secret Key', 'fluent-cart'),
                                'type' => 'password',
                                'placeholder' => __('sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'fluent-cart'),
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
                            'live_public_key' => array(
                                'value' => '',
                                'label' => __('Live Public Key', 'fluent-cart'),
                                'type' => 'text',
                                'placeholder' => __('pk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'fluent-cart'),
                                'dependency' => [
                                    'depends_on' => 'payment_mode',
                                    'operator' => '=',
                                    'value' => 'live'
                                ]
                            ),
                            'live_secret_key' => array(
                                'value' => '',
                                'label' => __('Live Secret Key', 'fluent-cart'),
                                'type' => 'password',
                                'placeholder' => __('sk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'fluent-cart'),
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
        $publicKey = $data['public_key'] ?? '';
        $secretKey = $data['secret_key'] ?? '';

        if (empty($publicKey) || empty($secretKey)) {
            return [
                'status' => 'failed',
                'message' => __('Public Key and Secret Key are required', 'fluent-cart')
            ];
        }

        if (!str_starts_with($publicKey, 'pk_test_') && !str_starts_with($publicKey, 'pk_live_')) {
            return [
                'status' => 'failed',
                'message' => __('Invalid Paystack Public Key format', 'fluent-cart')
            ];
        }

        if (!str_starts_with($secretKey, 'sk_test_') && !str_starts_with($secretKey, 'sk_live_')) {
            return [
                'status' => 'failed',
                'message' => __('Invalid Paystack Secret Key format', 'fluent-cart')
            ];
        }

        try {
            $testResponse = static::testApiConnection($secretKey);
            
            return [
                'status' => 'success',
                'message' => __('Paystack settings validated successfully', 'fluent-cart')
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
                'handle' => 'paystack-inline-js',
                'src' => 'https://js.paystack.co/v1/inline.js',
            ],
            [
                'handle' => 'fluent-cart-paystack-checkout',
                'src' => Vite::getEnqueuePath('public/payment-methods/paystack-checkout.js'),
                'deps' => ['paystack-inline-js']
            ]
        ];
    }

    public function getEnqueueStyleSrc(): array
    {
        return [
            [
                'handle' => 'fluent-cart-paystack-styles',
                'src' => Vite::getEnqueuePath('public/payment-methods/paystack.css'),
            ]
        ];
    }

    private function initializePaystackTransaction($data)
    {
        $secretKey = $this->settings->getSecretKey();
        
        $response = wp_remote_post('https://api.paystack.co/transaction/initialize', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    private function processPaystackRefund($refundData)
    {
        $secretKey = $this->settings->getSecretKey();
        
        $response = wp_remote_post('https://api.paystack.co/refund', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($refundData),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    private static function testApiConnection($secretKey)
    {
        $response = wp_remote_get('https://api.paystack.co/bank', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (wp_remote_retrieve_response_code($response) !== 200 || !$data['status']) {
            throw new \Exception(__('Invalid Paystack credentials', 'fluent-cart'));
        }

        return true;
    }

    private function verifyWebhookSignature($payload)
    {
        $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
        $webhookSecret = $this->settings->getWebhookSecret();
        
        if (!$signature || !$webhookSecret) {
            return false;
        }

        $expectedSignature = hash_hmac('sha512', json_encode($payload), $webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    private function generateReference($order)
    {
        return 'fc_' . $order->uuid . '_' . time();
    }

    private function getWebhookUrl()
    {
        return site_url('?fluent_cart_payment_api_notify=paystack');
    }

    public function webHookPaymentMethodName()
    {
        return $this->getMeta('route');
    }

    private function getCallbackUrl($order)
    {
        return add_query_arg([
            'fluent_cart_payment' => 'paystack',
            'order_id' => $order->id,
            'payment_hash' => $order->payment_hash
        ], site_url());
    }

    private function handleChargeSuccess($payload)
    {
        $transaction = $payload['data'] ?? [];
        $orderId = $transaction['metadata']['order_id'] ?? '';
        
        if ($orderId) {
            $order = fluent_cart_get_order($orderId);
            if ($order) {
                $this->handlePaymentSuccess($transaction, $order);
            }
        }
    }

    private function handleChargeFailed($payload)
    {
        $transaction = $payload['data'] ?? [];
        $orderId = $transaction['metadata']['order_id'] ?? '';
        
        if ($orderId) {
            $order = fluent_cart_get_order($orderId);
            if ($order) {
                $this->handlePaymentFailure($transaction, $order);
            }
        }
    }

    private function handleRefundProcessed($payload)
    {
        $refund = $payload['data'] ?? [];
        // Process refund webhook logic here
    }

    private function handlePaymentSuccess($transaction, $order)
    {
        $order->payment_status = 'paid';
        $order->status = 'processing';
        $order->vendor_charge_id = $transaction['reference'];
        $order->save();

        do_action('fluent_cart/payment_success', [
            'order' => $order,
            'payment_intent' => $transaction
        ]);
    }

    private function handlePaymentFailure($transaction, $order)
    {
        $order->payment_status = 'failed';
        $order->status = 'failed';
        $order->save();

        do_action('fluent_cart/payment_failed', [
            'order' => $order,
            'payment_intent' => $transaction
        ]);
    }
}
