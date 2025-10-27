<?php

namespace FluentCartPro\app\Modules\Integrations;

use FluentCart\App\Helpers\EditorShortCodeHelper;
use FluentCart\App\Models\Order;
use FluentCart\App\Modules\Integrations\BaseIntegrationManager;
use FluentCart\App\Vite;
use FluentCart\Framework\Support\Arr;

class WebhookConnect extends BaseIntegrationManager
{

    public $scopes = ['global', 'product'];

    public $integrationId = null;

    public function __construct()
    {
        parent::__construct('Webhook', 'webhook', 10);

        $this->description = __('Send data anywhere via webhook', 'fluent-cart');
        $this->logo = Vite::getAssetUrl('images/integrations/webhook.svg');
        $this->disableGlobalSettings = true;
    }

    public function isConfigured()
    {
        return true;
    }

    public function getApiSettings()
    {
        return [
            'status'  => true,
            'api_key' => ''
        ];
    }

    public function getIntegrationDefaults($settings)
    {
        return [
            'enabled'         => 'yes',
            'name'            => '',
            'request_url'     => '',
            'request_method'  => 'post',
            'request_format'  => 'json',
            'request_headers' => [
                'type' => 'no_headers',
                'data' => [
                    [
                        'name'  => '',
                        'value' => ''
                    ]
                ]
            ],
            'request_body'    => [
                'type' => 'all_data',
                'data' => [
                    [
                        'name'  => '',
                        'value' => ''
                    ]
                ]
            ],
            'event_trigger'   => [],
        ];
    }

    public function getSettingsFields($settings, $args = [])
    {
        $bodyOptions = (EditorShortCodeHelper::getShortCodes())['data'];

        $fields = [
            'name'            => [
                'key'         => 'name',
                'label'       => __('Integration Title', 'fluent-cart'),
                'required'    => true,
                'placeholder' => __('Name', 'fluent-cart'),
                'component'   => 'text',
                'inline_tip'  => __('Name of this feed, it will be used to identify this integration in the list of integrations', 'fluent-cart')
            ],
            'request_url'     => [
                'key'         => 'request_url',
                'label'       => __('Request URL', 'fluent-cart'),
                'required'    => true,
                'placeholder' => __('https://example.com/webhook-endpoint', 'fluent-cart'),
                'component'   => 'text',
                'inline_tip'  => __('The URL to which the webhook request will be sent', 'fluent-cart')
            ],
            'request_method'  => [
                'key'        => 'request_method',
                'label'      => __('Request Method', 'fluent-cart'),
                'required'   => true,
                'component'  => 'select',
                'options'    => [
                    'post'   => __('POST', 'fluent-cart'),
                    'get'    => __('GET', 'fluent-cart'),
                    'put'    => __('PUT', 'fluent-cart'),
                    'patch'  => __('PATCH', 'fluent-cart'),
                    'delete' => __('DELETE', 'fluent-cart'),
                ],
                'inline_tip' => __('The HTTP method to use for the webhook request', 'fluent-cart')
            ],
            'request_format'  => [
                'key'        => 'request_format',
                'label'      => __('Request Format', 'fluent-cart'),
                'required'   => true,
                'component'  => 'select',
                'options'    => [
                    'json'      => __('JSON', 'fluent-cart'),
                    'form_data' => __('Form Data', 'fluent-cart'),
                ],
                'inline_tip' => __('The format in which the data will be sent', 'fluent-cart')
            ],
            'request_headers' => [
                'key'             => 'request_headers',
                'label'           => __('Request Headers', 'fluent-cart'),
                'required'        => true,
                'component'       => 'custom_component',
                'render_template' => $this->getHeaderComponent($settings),
                'inline_tip'      => __('Custom headers to include in the webhook request', 'fluent-cart')
            ],
            'request_body'    => [
                'key'               => 'request_body',
                'label'             => __('Request Body', 'fluent-cart'),
                'required'          => true,
                'component'         => 'custom_component',
                'render_template'   => $this->getBodyComponent($settings),
                'smartcode_options' => $bodyOptions,
                'inline_tip'        => __('The data sent in the request body.', 'fluent-cart')
            ],
        ];

        $fields = array_values($fields);
        $fields[] = $this->actionFields();

        return [
            'fields'              => $fields,
            'button_require_list' => false,
            'integration_title'   => __('Webhook', 'fluent-cart')
        ];
    }

    private function getHeaderComponent($settings)
    {
        ob_start();
        ?>
        <div class="fct_webhook_header_config">
            <div class="fc-setting-form-fields self-center">
                <el-radio-group v-model="settings.request_headers.type">
                    <el-radio value="no_headers">No Headers</el-radio>
                    <el-radio value="custom_headers">With Headers</el-radio>
                </el-radio-group>

                <div v-if="settings.request_headers.type === 'custom_headers'" class="mt-4">
                    <div class="mb-2 font-medium">Custom Headers</div>
                    <div v-for="(field, index) in settings.request_headers.data" :key="index"
                         class="flex items-center gap-2 mb-2">
                        <el-input size="small" v-model="field.name" placeholder="Header Name"></el-input>
                        <el-input size="small" v-model="field.value" placeholder="Header Value"></el-input>
                        <el-button size="small" :disabled="settings.request_headers.data.length === 1" type="danger"
                                   @click="settings.request_headers.data.splice(index, 1)">-
                        </el-button>
                    </div>
                    <el-button @click="settings.request_headers.data.push({ name: '', value: '' })">+ Add more
                    </el-button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function getBodyComponent($settings)
    {

        ob_start();
        ?>
        <div class="fct_webhook_header_config">
            <div class="fc-setting-form-fields self-center">
                <el-radio-group v-model="settings.request_body.type">
                    <el-radio value="all_data">All Data</el-radio>
                    <el-radio value="selected_fields">Selected Fields</el-radio>
                </el-radio-group>

                <div v-if="settings.request_body.type === 'selected_fields'" class="mt-4">
                    <div class="mb-2 font-medium">{{$t('Map Payload Data')}}</div>
                    <div v-for="(dataGroup, index) in settings.request_body.data" :key="index"
                         class="flex items-center gap-2 mb-2">
                        <el-input size="small" v-model="dataGroup.name" placeholder="Payload Key"></el-input>
                        <el-select size="small" v-model="dataGroup.value" placeholder="Select Value" filterable>
                            <el-option-group v-for="optionGroup in field.smartcode_options" :key="optionGroup.key"
                                             :label="optionGroup.title">
                                <el-option v-for="(option, optionKey) in optionGroup.shortcodes" :key="optionKey"
                                           :label="option" :value="optionKey"/>
                            </el-option-group>
                        </el-select>

                        <el-button size="small" :disabled="settings.request_body.data.length === 1" type="danger"
                                   @click="settings.request_body.data.splice(index, 1)">-
                        </el-button>
                    </div>
                    <el-button @click="settings.request_body.data.push({ name: '', value: '' })">
                        + Add more
                    </el-button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    function processAction($order, $eventData)
    {
        $feedConfig = Arr::get($eventData, 'feed', []);

        if (empty($feedConfig['request_url']) || filter_var($feedConfig['request_url'], FILTER_VALIDATE_URL) === false) {
            return;
        }

        $payloadBody = [];

        $requestBodyType = Arr::get($feedConfig, 'request_body.type', 'all_data');

        if ($requestBodyType == 'all_data') {
            $fillables = (new Order())->getFillable();
            $fillables[] = 'id';
            $payloadBody = [
                'order'            => Arr::only($order->toArray(), $fillables),
                'customer'         => $order->customer ? $order->customer->toArray() : [],
                'transactions'     => $order->transactions ? $order->transactions->toArray() : [],
                'order_items'      => $order->order_items ? $order->order_items->toArray() : [],
                'subscriptions'    => $order->subscriptions ? $order->subscriptions->toArray() : [],
                'tax_rates'        => $order->orderTaxRates ? $order->orderTaxRates->toArray() : [],
                'shipping_address' => $order->shippingAddress ? $order->shippingAddress->toArray() : [],
                'billing_address'  => $order->billingAddress ? $order->billingAddress->toArray() : [],
            ];
        } else {
            $selectedFields = Arr::get($feedConfig, 'request_body.data', []);
            foreach ($selectedFields as $field) {
                if (empty($field['name']) || empty($field['value'])) {
                    continue;
                }
                $payloadBody[$field['name']] = $this->parseSmartCode($field['value'], $order);
            }
        }

        $headers = [];
        if (Arr::get($feedConfig, 'request_headers.type') == 'custom_headers') {
            foreach (Arr::get($feedConfig, 'request_headers.data', []) as $item) {
                if (empty($item['name']) || empty($item['value'])) {
                    continue;
                }
                $headers[$item['name']] = $item['value'];
            }
        }

        $requestMethod = Arr::get($feedConfig, 'request_method', 'post');
        $requestFormat = Arr::get($feedConfig, 'request_format', 'json');

        if ($requestFormat === 'json') {
            $headers['Content-Type'] = 'application/json; charset=utf-8';
            $payloadBody = json_encode($payloadBody);
        } else {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=utf-8';
        }

        $response = wp_remote_request($feedConfig['request_url'], [
            'method'  => strtoupper($requestMethod),
            'headers' => $headers,
            'body'    => $payloadBody,
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            $order->addLog(
                __('Webhook Request Failed', 'fluent-cart'),
                'Integration: ' . Arr::get($feedConfig, 'name', '') . ' failed. Error: ' . $response->get_error_message(),
                'error',
                'Webhook Integration'
            );
            return;
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        if ($responseCode < 200 || $responseCode >= 300) {
            $order->addLog(
                __('Webhook Request Failed', 'fluent-cart'),
                'Integration: ' . Arr::get($feedConfig, 'name', '') . ' failed. Response Code: ' . $responseCode,
                'error',
                'Webhook Integration'
            );
            return;
        }

        $order->addLog(
            __('Webhook Request Sent', 'fluent-cart'),
            'Integration: ' . Arr::get($feedConfig, 'name', '') . ' sent successfully. Response Code: ' . $responseCode,
            'info',
            'Webhook Integration'
        );
    }
}
