<?php
/** @var array $notifications */
/** @var string $aiKey */
?>
<div class="wrap">
    <h1><?php esc_html_e('Support Settings', 'fluent-cart'); ?></h1>

    <h2><?php esc_html_e('Notification Settings', 'fluent-cart'); ?></h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:600px;">
        <?php wp_nonce_field('fluentcart_support_notifications'); ?>
        <input type="hidden" name="action" value="fluentcart_support_save_notifications" />

        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row"><?php esc_html_e('Email Notifications', 'fluent-cart'); ?></th>
                <td><label><input type="checkbox" name="notify_admin" value="1" <?php checked(!empty($notifications['notify_admin'])); ?> /> <?php esc_html_e('Notify site admin on new tickets', 'fluent-cart'); ?></label></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Inbox Notifications', 'fluent-cart'); ?></th>
                <td><label><input type="checkbox" name="notify_inbox_email" value="1" <?php checked(!empty($notifications['notify_inbox_email'])); ?> /> <?php esc_html_e('Send email to inbox address', 'fluent-cart'); ?></label></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Internal Logs', 'fluent-cart'); ?></th>
                <td><label><input type="checkbox" name="log_internal" value="1" <?php checked(!empty($notifications['log_internal'])); ?> /> <?php esc_html_e('Keep internal notification log inside ticket meta', 'fluent-cart'); ?></label></td>
            </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save Notification Settings', 'fluent-cart')); ?>
    </form>

    <hr />

    <h2><?php esc_html_e('AI Assistant', 'fluent-cart'); ?></h2>
    <p><?php esc_html_e('Provide an OpenAI API key to enable automated reply suggestions when handling tickets.', 'fluent-cart'); ?></p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:600px;">
        <?php wp_nonce_field('fluentcart_support_ai'); ?>
        <input type="hidden" name="action" value="fluentcart_support_save_ai" />

        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row"><label for="fluentcart-ai-api-key"><?php esc_html_e('OpenAI API Key', 'fluent-cart'); ?></label></th>
                <td><input type="password" name="ai_api_key" id="fluentcart-ai-api-key" class="regular-text" value="<?php echo esc_attr($aiKey); ?>" placeholder="sk-..." /></td>
            </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save AI Settings', 'fluent-cart')); ?>
    </form>
</div>
