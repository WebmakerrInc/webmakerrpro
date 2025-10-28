<?php

namespace FluentSuite;

class Plugin
{
    /**
     * @var string
     */
    protected $pluginFile;

    /**
     * @var Loader
     */
    protected $loader;

    /**
     * @var array<string, array<string, string>>
     */
    protected $availableModules = [
        'fluent_support' => [
            'class'       => Modules\FluentSupport\Module::class,
            'name'        => 'Fluent Support Core',
            'description' => 'Ticket management, agent workspace, and OpenAI powered auto replies.'
        ],
    ];

    public static function boot(string $pluginFile): self
    {
        $instance = new static($pluginFile);
        $instance->init();

        return $instance;
    }

    public static function activate(): void
    {
        if (!get_option('fluent_suite_active_modules')) {
            update_option('fluent_suite_active_modules', []);
        }
    }

    public static function deactivate(): void
    {
        // Keep data intact intentionally. Hook reserved for future cleanup.
    }

    public function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    protected function init(): void
    {
        $this->loader = new Loader($this->availableModules, $this->getActiveModules());
        add_action('init', [$this, 'registerAssets']);
        add_action('admin_menu', [$this, 'registerDashboard']);
        add_action('rest_api_init', [$this, 'registerApi']);
        add_action('admin_init', [$this, 'maybeHandleDashboardActions']);
        $this->loader->boot();
    }

    public function registerAssets(): void
    {
        wp_register_style(
            'fluent-suite-admin',
            FLUENT_SUITE_URL . 'assets/css/admin.css',
            [],
            FLUENT_SUITE_VERSION
        );
    }

    public function registerApi(): void
    {
        register_rest_route('fluent-suite/v1', '/modules', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getModulesResponse'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
    }

    public function getModulesResponse(): array
    {
        $modules = [];
        $active = $this->getActiveModules();
        foreach ($this->availableModules as $slug => $module) {
            $modules[] = [
                'slug'        => $slug,
                'name'        => $module['name'],
                'description' => $module['description'],
                'active'      => in_array($slug, $active, true)
            ];
        }

        return [
            'modules' => $modules
        ];
    }

    protected function getActiveModules(): array
    {
        $active = get_option('fluent_suite_active_modules', []);
        if (!is_array($active)) {
            $active = [];
        }

        return array_values(array_intersect(array_keys($this->availableModules), $active));
    }

    public function registerDashboard(): void
    {
        add_menu_page(
            __('Fluent Suite', 'fluent-suite'),
            __('Fluent Suite', 'fluent-suite'),
            'manage_options',
            'fluent-suite',
            [$this, 'renderDashboard'],
            'dashicons-hammer',
            56
        );

        add_submenu_page(
            'fluent-suite',
            __('Dashboard', 'fluent-suite'),
            __('Dashboard', 'fluent-suite'),
            'manage_options',
            'fluent-suite',
            [$this, 'renderDashboard']
        );
    }

    public function renderDashboard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'fluent-suite'));
        }

        wp_enqueue_style('fluent-suite-admin');
        $activeModules = $this->getActiveModules();
        $apiKey        = get_option('fluent_suite_openai_api_key', '');

        include FLUENT_SUITE_PATH . 'views/dashboard.php';
    }

    public function maybeHandleDashboardActions(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['fluent_suite_settings_nonce'])) {
            return;
        }

        check_admin_referer('fluent_suite_save_settings', 'fluent_suite_settings_nonce');

        $activeModules = array_map('sanitize_text_field', (array)($_POST['fluent_suite_modules'] ?? []));
        $activeModules = array_values(array_intersect(array_keys($this->availableModules), $activeModules));

        update_option('fluent_suite_active_modules', $activeModules);

        $apiKey = isset($_POST['fluent_suite_openai_api_key']) ? trim((string)$_POST['fluent_suite_openai_api_key']) : '';
        if ($apiKey) {
            $apiKey = sanitize_text_field($apiKey);
        }
        update_option('fluent_suite_openai_api_key', $apiKey);

        $this->loader->refresh($activeModules);

        $referer = wp_get_referer() ?: admin_url('admin.php?page=fluent-suite');
        wp_safe_redirect(add_query_arg('fs_settings_saved', 'yes', $referer));
        exit;
    }
}
