<?php
/** @var array $activeModules */
/** @var array $this->availableModules */
/** @var string $apiKey */
?>
<div class="wrap fluent-suite-dashboard">
    <h1><?php esc_html_e('Fluent Suite Dashboard', 'fluent-suite'); ?></h1>

    <?php if (!empty($_GET['fs_settings_saved'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings updated successfully.', 'fluent-suite'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=fluent-suite')); ?>">
        <?php wp_nonce_field('fluent_suite_save_settings', 'fluent_suite_settings_nonce'); ?>

        <h2><?php esc_html_e('Modules', 'fluent-suite'); ?></h2>
        <p class="description"><?php esc_html_e('Enable only the modules you need. Modules load on demand to keep the suite lightweight.', 'fluent-suite'); ?></p>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Module', 'fluent-suite'); ?></th>
                    <th><?php esc_html_e('Description', 'fluent-suite'); ?></th>
                    <th><?php esc_html_e('Active', 'fluent-suite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->availableModules as $slug => $module) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($module['name']); ?></strong>
                        </td>
                        <td><?php echo esc_html($module['description']); ?></td>
                        <td>
                            <label>
                                <input type="checkbox" name="fluent_suite_modules[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $activeModules, true)); ?> />
                                <?php esc_html_e('Enabled', 'fluent-suite'); ?>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2><?php esc_html_e('Integrations', 'fluent-suite'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="fluent_suite_openai_api_key"><?php esc_html_e('OpenAI API Key', 'fluent-suite'); ?></label>
                </th>
                <td>
                    <input type="password" name="fluent_suite_openai_api_key" id="fluent_suite_openai_api_key" class="regular-text" value="<?php echo esc_attr($apiKey); ?>" />
                    <p class="description"><?php esc_html_e('Provide an OpenAI API key to enable automated ticket replies in Fluent Support.', 'fluent-suite'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Settings', 'fluent-suite')); ?>
    </form>
</div>
