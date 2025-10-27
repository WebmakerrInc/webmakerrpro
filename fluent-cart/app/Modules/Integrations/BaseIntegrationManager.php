<?php

namespace FluentCart\App\Modules\Integrations;

use FluentCart\App\App;
use FluentCart\App\Helpers\Status;
use FluentCart\App\Models\Order;
use FluentCart\App\Services\ShortCodeParser\ShortcodeTemplateBuilder;
use FluentCart\Framework\Support\Arr;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

abstract class BaseIntegrationManager
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $slug;

    /**
     * @var int
     */
    public $priority = 10;

    /**
     * @var array
     */
    public $scopes = ['global'];

    /**
     * @var string|null
     */
    public $integrationId = null;

    /**
     * @var string
     */
    public $description = '';

    /**
     * @var string
     */
    public $logo = '';

    /**
     * @var bool
     */
    public $disableGlobalSettings = false;

    /**
     * @var array<string, self>
     */
    protected static $integrations = [];

    /**
     * @var bool
     */
    protected static $routesRegistered = false;

    /**
     * @var bool
     */
    protected static $eventsRegistered = false;

    /**
     * Option key to persist global feeds
     *
     * @var string
     */
    protected static $globalFeedsOption = '_fct_integration_global_feeds';

    /**
     * Option key to persist global settings
     *
     * @var string
     */
    protected static $globalSettingsOption = '_fct_integration_settings';

    /**
     * Constructor.
     */
    public function __construct(string $title, string $slug, int $priority = 10)
    {
        $this->title = $title;
        $this->slug = $slug;
        $this->priority = $priority;
        $this->integrationId = $slug;
    }

    /**
     * Register the integration manager.
     */
    public function register(): void
    {
        static::$integrations[$this->slug] = $this;

        if (!static::$routesRegistered) {
            static::registerRoutes();
        }

        if (!static::$eventsRegistered) {
            static::registerEventHooks();
        }
    }

    /**
     * Get integration defaults.
     */
    abstract public function getIntegrationDefaults($settings);

    /**
     * Get settings fields for integration feed.
     */
    abstract public function getSettingsFields($settings, $args = []);

    /**
     * Handle the integration action when the event fires.
     */
    abstract public function processAction($order, $eventData);

    /**
     * Provide global settings fields.
     */
    public function getGlobalSettingsFields(): array
    {
        return [];
    }

    /**
     * Provide default global settings.
     */
    public function getGlobalSettingsDefaults(): array
    {
        return [
            'status' => false,
        ];
    }

    /**
     * Return action fields for the integration feed editor.
     */
    public function actionFields(): array
    {
        return Status::eventTriggers();
    }

    /**
     * Parse a smart code using the provided order context.
     */
    protected function parseSmartCode(string $code, Order $order, array $eventData = [])
    {
        $context = $this->buildSmartCodeContext($order, $eventData);

        return ShortcodeTemplateBuilder::make($code, $context);
    }

    /**
     * Prepare the smart code context for the parser.
     */
    protected function buildSmartCodeContext(Order $order, array $eventData = []): array
    {
        $order->loadMissing([
            'customer',
            'transactions',
            'order_items',
            'subscriptions',
            'orderTaxRates',
            'shippingAddress',
            'billingAddress',
        ]);

        $transaction = $order->transactions ? $order->transactions->first() : null;

        $context = [
            'order'       => $order->toArray(),
            'customer'    => $order->customer ? $order->customer->toArray() : [],
            'transaction' => $transaction ? $transaction->toArray() : [],
        ];

        if (!empty($eventData['subscription']) && method_exists($eventData['subscription'], 'toArray')) {
            $context['subscription'] = $eventData['subscription']->toArray();
        }

        if (!empty($eventData['feed'])) {
            $context['feed'] = $eventData['feed'];
        }

        return $context;
    }

    /**
     * Register REST API routes for integrations.
     */
    protected static function registerRoutes(): void
    {
        add_action('rest_api_init', function () {
            $namespace = App::config()->get('app.rest_namespace');
            $version = App::config()->get('app.rest_version');
            $base = $namespace . '/' . $version;

            register_rest_route($base, '/integration/global-feeds', [
                [
                    'methods'             => 'GET',
                    'callback'            => [static::class, 'restGetGlobalFeeds'],
                    'permission_callback' => [static::class, 'verifyPermissions'],
                ],
            ]);

            register_rest_route($base, '/integration/global-feeds/settings', [
                [
                    'methods'             => 'GET',
                    'callback'            => [static::class, 'restGetFeedSettings'],
                    'permission_callback' => [static::class, 'verifyPermissions'],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [static::class, 'restSaveFeedSettings'],
                    'permission_callback' => [static::class, 'verifyPermissions'],
                ],
            ]);

            register_rest_route($base, '/integration/global-feeds/(?P<feed_id>[a-zA-Z0-9_-]+)', [
                [
                    'methods'             => 'DELETE',
                    'callback'            => [static::class, 'restDeleteFeed'],
                    'permission_callback' => [static::class, 'verifyPermissions'],
                ],
            ]);

            register_rest_route($base, '/integration/global-feeds/change-status/(?P<feed_id>[a-zA-Z0-9_-]+)', [
                [
                    'methods'             => 'POST',
                    'callback'            => [static::class, 'restUpdateFeedStatus'],
                    'permission_callback' => [static::class, 'verifyPermissions'],
                ],
            ]);

            register_rest_route($base, '/integration/global-settings', [
                [
                    'methods'             => 'GET',
                    'callback'            => [static::class, 'restGetGlobalSettings'],
                    'permission_callback' => [static::class, 'verifyPermissions'],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [static::class, 'restSaveGlobalSettings'],
                    'permission_callback' => [static::class, 'verifyPermissions'],
                ],
            ]);

            register_rest_route($base, '/integration/global-settings(?P<action>/[a-zA-Z0-9_-]+)?', [
                [
                    'methods'             => 'POST',
                    'callback'            => [static::class, 'restHandleGlobalAction'],
                    'permission_callback' => [static::class, 'verifyPermissions'],
                ],
            ]);

            register_rest_route($base, '/integration/addons', [
                [
                    'methods'             => 'GET',
                    'callback'            => [static::class, 'restGetAddons'],
                    'permission_callback' => [static::class, 'verifyPermissions'],
                ],
            ]);

            register_rest_route($base, '/integration/feed/dynamic_options', [
                [
                    'methods'             => 'GET',
                    'callback'            => [static::class, 'restGetDynamicOptions'],
                    'permission_callback' => [static::class, 'verifyPermissions'],
                ],
            ]);

            register_rest_route($base, '/integration/feed/chained', [
                [
                    'methods'             => 'POST',
                    'callback'            => [static::class, 'restGetChainedOptions'],
                    'permission_callback' => [static::class, 'verifyPermissions'],
                ],
            ]);

            register_rest_route($base, '/integration/feed/lists', [
                [
                    'methods'             => 'GET',
                    'callback'            => [static::class, 'restGetLists'],
                    'permission_callback' => [static::class, 'verifyPermissions'],
                ],
            ]);

            register_rest_route($base, '/integration/feed/install-plugin', [
                [
                    'methods'             => 'POST',
                    'callback'            => [static::class, 'restInstallAddon'],
                    'permission_callback' => [static::class, 'verifyPermissions'],
                ],
            ]);
        });

        static::$routesRegistered = true;
    }

    /**
     * Register event hooks for integration feeds.
     */
    protected static function registerEventHooks(): void
    {
        $events = [];
        $triggerFields = Status::eventTriggers();

        if (!empty($triggerFields['options'])) {
            foreach ($triggerFields['options'] as $group) {
                foreach (Arr::get($group, 'options', []) as $event) {
                    $key = Arr::get($event, 'value');
                    if ($key) {
                        $events[] = $key;
                    }
                }
            }
        }

        $events = array_unique($events);

        foreach ($events as $event) {
            add_action('fluent_cart/' . $event, [static::class, 'handleEventTrigger'], 10, 1);
        }

        static::$eventsRegistered = true;
    }

    /**
     * Handle event trigger for all integrations.
     */
    public static function handleEventTrigger($eventData): void
    {
        $current = current_filter();
        $eventKey = str_replace('fluent_cart/', '', $current);

        $feeds = static::getGlobalFeeds();
        if (!$feeds) {
            return;
        }

        foreach ($feeds as $feedId => $feed) {
            if (empty($feed['enabled'])) {
                continue;
            }

            $triggers = Arr::get($feed, 'feed.event_trigger', []);
            if (!$triggers || !in_array($eventKey, $triggers, true)) {
                continue;
            }

            $integration = static::getIntegration(Arr::get($feed, 'integration_name'));
            if (!$integration) {
                continue;
            }

            $order = Arr::get($eventData, 'order');

            if (!$order instanceof Order) {
                $orderId = Arr::get($eventData, 'order_id');
                if ($orderId) {
                    $order = Order::with([
                        'customer',
                        'transactions',
                        'order_items',
                        'subscriptions',
                        'orderTaxRates',
                        'shippingAddress',
                        'billingAddress',
                    ])->find($orderId);
                }
            }

            if (!$order instanceof Order) {
                continue;
            }

            $payload = is_array($eventData) ? $eventData : [];
            $payload['feed'] = $feed['feed'];
            $payload['feed_id'] = $feedId;
            $payload['integration'] = $feed;
            $payload['event_key'] = $eventKey;

            try {
                $integration->processAction($order, $payload);
                static::markFeedRan($feedId);
            } catch (\Throwable $exception) {
                static::logFeedError($feedId, $exception->getMessage());
            }
        }
    }

    /**
     * Register a successful run for a feed.
     */
    protected static function markFeedRan(string $feedId): void
    {
        $feeds = static::getGlobalFeeds();
        if (!isset($feeds[$feedId])) {
            return;
        }

        $feeds[$feedId]['last_ran_at'] = current_time('mysql');
        $feeds[$feedId]['last_error'] = '';

        static::storeGlobalFeeds($feeds);
    }

    /**
     * Log an error for a feed.
     */
    protected static function logFeedError(string $feedId, string $message): void
    {
        $feeds = static::getGlobalFeeds();
        if (!isset($feeds[$feedId])) {
            return;
        }

        $feeds[$feedId]['last_error'] = $message;
        $feeds[$feedId]['last_ran_at'] = current_time('mysql');

        static::storeGlobalFeeds($feeds);
    }

    /**
     * Retrieve registered integration instance.
     */
    protected static function getIntegration(?string $slug): ?self
    {
        if (!$slug) {
            return null;
        }

        return static::$integrations[$slug] ?? null;
    }

    /**
     * Permission callback for REST routes.
     */
    public static function verifyPermissions(): bool
    {
        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            return true;
        }

        if (class_exists('FluentCart\\App\\Services\\Permission\\PermissionManager')) {
            return \FluentCart\App\Services\Permission\PermissionManager::hasPermission('store/settings');
        }

        return false;
    }

    /**
     * REST: Fetch global feeds.
     */
    public static function restGetGlobalFeeds(WP_REST_Request $request)
    {
        $feeds = static::getGlobalFeeds();
        $responseFeeds = [];

        foreach ($feeds as $id => $feed) {
            $responseFeeds[] = array_merge($feed, [
                'id'      => $id,
                'enabled' => (bool) Arr::get($feed, 'enabled', true),
            ]);
        }

        $available = [];
        foreach (static::$integrations as $slug => $integration) {
            $available[$slug] = [
                'id'                     => $slug,
                'title'                  => $integration->title,
                'description'            => $integration->description,
                'logo'                   => $integration->logo,
                'scopes'                 => $integration->scopes,
                'enabled'                => true,
                'disable_global_settings' => (bool) $integration->disableGlobalSettings,
            ];
        }

        return new WP_REST_Response([
            'feeds'                 => $responseFeeds,
            'available_integrations'=> $available,
            'all_module_config_url' => '',
        ]);
    }

    /**
     * REST: Fetch feed settings for create/edit.
     */
    public static function restGetFeedSettings(WP_REST_Request $request)
    {
        $integrationName = $request->get_param('integration_name');
        $integration = static::getIntegration($integrationName);

        if (!$integration) {
            return new WP_Error('fct_invalid_integration', __('Invalid integration requested.', 'fluent-cart'));
        }

        $feedId = $request->get_param('integration_id');
        $feeds = static::getGlobalFeeds();
        $settings = $integration->getIntegrationDefaults([]);

        if ($feedId && isset($feeds[$feedId])) {
            $settings = Arr::get($feeds[$feedId], 'feed', $settings);
        }

        $settings = wp_parse_args($settings, $integration->getIntegrationDefaults($settings));

        return new WP_REST_Response([
            'settings_fields' => $integration->getSettingsFields($settings, [
                'integration_id' => $feedId,
            ]),
            'settings'        => $settings,
            'integration_title' => $integration->title,
            'integration_logo'  => $integration->logo,
            'merge_fields'      => [],
        ]);
    }

    /**
     * REST: Save feed settings.
     */
    public static function restSaveFeedSettings(WP_REST_Request $request)
    {
        $integrationName = $request->get_param('integration_name');
        $integration = static::getIntegration($integrationName);

        if (!$integration) {
            return new WP_Error('fct_invalid_integration', __('Invalid integration requested.', 'fluent-cart'));
        }

        $payload = $request->get_param('integration');
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $settings = wp_parse_args($payload, $integration->getIntegrationDefaults($payload));
        $settings['event_trigger'] = array_values(array_filter(Arr::get($settings, 'event_trigger', [])));
        $settings['enabled'] = Arr::get($settings, 'enabled', 'yes');

        $feeds = static::getGlobalFeeds();
        $feedId = $request->get_param('integration_id');
        $isNew = empty($feedId) || !isset($feeds[$feedId]);

        if ($isNew) {
            $feedId = uniqid($integration->slug . '_', false);
        }

        $feeds[$feedId] = [
            'integration_name'  => $integration->slug,
            'integration_title' => $integration->title,
            'logo'              => $integration->logo,
            'description'       => $integration->description,
            'feed'              => $settings,
            'enabled'           => ($settings['enabled'] === 'yes'),
            'created_at'        => $isNew ? current_time('mysql') : Arr::get($feeds[$feedId], 'created_at', current_time('mysql')),
            'updated_at'        => current_time('mysql'),
            'last_ran_at'       => Arr::get($feeds[$feedId], 'last_ran_at'),
            'last_error'        => Arr::get($feeds[$feedId], 'last_error', ''),
        ];

        static::storeGlobalFeeds($feeds);

        return new WP_REST_Response([
            'message' => $isNew
                ? __('Integration feed has been created successfully.', 'fluent-cart')
                : __('Integration feed has been updated successfully.', 'fluent-cart'),
            'created' => $isNew,
            'feed_id' => $feedId,
        ]);
    }

    /**
     * REST: Delete a feed.
     */
    public static function restDeleteFeed(WP_REST_Request $request)
    {
        $feedId = $request->get_param('feed_id');
        $feeds = static::getGlobalFeeds();

        if (!isset($feeds[$feedId])) {
            return new WP_Error('fct_feed_not_found', __('Requested feed could not be found.', 'fluent-cart'), [
                'status' => 404,
            ]);
        }

        unset($feeds[$feedId]);
        static::storeGlobalFeeds($feeds);

        return new WP_REST_Response([
            'message' => __('Integration feed has been deleted successfully.', 'fluent-cart'),
        ]);
    }

    /**
     * REST: Update feed status.
     */
    public static function restUpdateFeedStatus(WP_REST_Request $request)
    {
        $feedId = $request->get_param('feed_id');
        $feeds = static::getGlobalFeeds();

        if (!isset($feeds[$feedId])) {
            return new WP_Error('fct_feed_not_found', __('Requested feed could not be found.', 'fluent-cart'), [
                'status' => 404,
            ]);
        }

        $status = filter_var($request->get_param('status'), FILTER_VALIDATE_BOOLEAN);
        $feeds[$feedId]['enabled'] = $status;
        $feeds[$feedId]['feed']['enabled'] = $status ? 'yes' : 'no';
        $feeds[$feedId]['updated_at'] = current_time('mysql');

        static::storeGlobalFeeds($feeds);

        return new WP_REST_Response([
            'message' => $status
                ? __('Integration feed has been enabled.', 'fluent-cart')
                : __('Integration feed has been disabled.', 'fluent-cart'),
        ]);
    }

    /**
     * REST: Fetch global settings for integration.
     */
    public static function restGetGlobalSettings(WP_REST_Request $request)
    {
        $integrationName = $request->get_param('settings_key');
        $integration = static::getIntegration($integrationName);

        if (!$integration) {
            return new WP_Error('fct_invalid_integration', __('Invalid integration requested.', 'fluent-cart'));
        }

        $settings = static::getGlobalSettings();
        $integrationSettings = Arr::get($settings, $integrationName, []);
        $integrationSettings = wp_parse_args($integrationSettings, $integration->getGlobalSettingsDefaults());

        return new WP_REST_Response([
            'data' => [
                'integration' => $integrationSettings,
                'settings'    => $integration->getGlobalSettingsFields(),
                'status'      => Arr::get($integrationSettings, 'status', false),
            ],
        ]);
    }

    /**
     * REST: Save global settings for integration.
     */
    public static function restSaveGlobalSettings(WP_REST_Request $request)
    {
        $integrationName = $request->get_param('settings_key');
        $integration = static::getIntegration($integrationName);

        if (!$integration) {
            return new WP_Error('fct_invalid_integration', __('Invalid integration requested.', 'fluent-cart'));
        }

        $payload = $request->get_param('integration');
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $payload = wp_parse_args($payload, $integration->getGlobalSettingsDefaults());

        $settings = static::getGlobalSettings();
        $settings[$integrationName] = $payload;

        static::storeGlobalSettings($settings);

        return new WP_REST_Response([
            'data' => [
                'status'       => Arr::get($payload, 'status', false),
                'message'      => __('Global settings updated successfully.', 'fluent-cart'),
                'redirect_url' => '',
            ],
        ]);
    }

    /**
     * REST: Handle integration specific actions (connect/disconnect etc).
     */
    public static function restHandleGlobalAction(WP_REST_Request $request)
    {
        $action = trim((string) $request->get_param('action'), '/');
        return new WP_REST_Response([
            'data' => [
                'message' => sprintf(__('No handler registered for the "%s" action.', 'fluent-cart'), $action ?: 'default'),
            ],
        ]);
    }

    /**
     * REST: Retrieve available addons.
     */
    public static function restGetAddons(WP_REST_Request $request)
    {
        return new WP_REST_Response([
            'addons' => [],
        ]);
    }

    /**
     * REST: Retrieve dynamic options for feed builder.
     */
    public static function restGetDynamicOptions(WP_REST_Request $request)
    {
        return new WP_REST_Response([
            'options' => [],
        ]);
    }

    /**
     * REST: Retrieve chained options for feed builder.
     */
    public static function restGetChainedOptions(WP_REST_Request $request)
    {
        return new WP_REST_Response([
            'data' => [
                'categories'     => [],
                'subcategories'  => [],
                'reset_values'   => false,
            ],
        ]);
    }

    /**
     * REST: Retrieve lists for integrations.
     */
    public static function restGetLists(WP_REST_Request $request)
    {
        return new WP_REST_Response([
            'lists' => [],
        ]);
    }

    /**
     * REST: Install addon placeholder handler.
     */
    public static function restInstallAddon(WP_REST_Request $request)
    {
        return new WP_REST_Response([
            'message' => __('Addon installation is not supported in this environment.', 'fluent-cart'),
        ], 200);
    }

    /**
     * Retrieve stored global feeds.
     */
    protected static function getGlobalFeeds(): array
    {
        $feeds = get_option(static::$globalFeedsOption, []);
        return is_array($feeds) ? $feeds : [];
    }

    /**
     * Persist global feeds.
     */
    protected static function storeGlobalFeeds(array $feeds): void
    {
        update_option(static::$globalFeedsOption, $feeds, false);
    }

    /**
     * Retrieve stored global settings.
     */
    protected static function getGlobalSettings(): array
    {
        $settings = get_option(static::$globalSettingsOption, []);
        return is_array($settings) ? $settings : [];
    }

    /**
     * Persist global settings.
     */
    protected static function storeGlobalSettings(array $settings): void
    {
        update_option(static::$globalSettingsOption, $settings, false);
    }
}
