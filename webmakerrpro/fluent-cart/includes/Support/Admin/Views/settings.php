<?php
/** @var array $notifications */
/** @var string $aiKey */
?>
<div class="fc-layout-width px-4 py-6 space-y-6">
    <div class="space-y-1">
        <h1 class="text-lg font-semibold text-system-dark dark:text-gray-50"><?php esc_html_e('Support Settings', 'fluent-cart'); ?></h1>
        <p class="text-sm text-system-mid"><?php esc_html_e('Configure how FluentCart notifies your team and powers AI replies.', 'fluent-cart'); ?></p>
    </div>

    <div class="fc-card fc-card-border">
        <div class="fc-card-header">
            <div class="fc-card-header-left flex-1">
                <h2 class="fc-card-header-title"><?php esc_html_e('Notification Settings', 'fluent-cart'); ?></h2>
                <p class="text-sm text-system-mid mt-1"><?php esc_html_e('Choose who receives alerts when a new ticket arrives.', 'fluent-cart'); ?></p>
            </div>
        </div>
        <div class="fc-card-body">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-4">
                <?php wp_nonce_field('fluentcart_support_notifications'); ?>
                <input type="hidden" name="action" value="fluentcart_support_save_notifications" />

                <div class="space-y-3">
                    <label class="flex items-start gap-3 text-sm text-system-dark dark:text-gray-50">
                        <input type="checkbox" name="notify_admin" value="1" class="w-4 h-4 text-primary-500 border border-gray-outline rounded" <?php checked(!empty($notifications['notify_admin'])); ?> />
                        <span><?php esc_html_e('Notify site admin on new tickets', 'fluent-cart'); ?></span>
                    </label>
                    <label class="flex items-start gap-3 text-sm text-system-dark dark:text-gray-50">
                        <input type="checkbox" name="notify_inbox_email" value="1" class="w-4 h-4 text-primary-500 border border-gray-outline rounded" <?php checked(!empty($notifications['notify_inbox_email'])); ?> />
                        <span><?php esc_html_e('Send email to inbox address', 'fluent-cart'); ?></span>
                    </label>
                    <label class="flex items-start gap-3 text-sm text-system-dark dark:text-gray-50">
                        <input type="checkbox" name="log_internal" value="1" class="w-4 h-4 text-primary-500 border border-gray-outline rounded" <?php checked(!empty($notifications['log_internal'])); ?> />
                        <span><?php esc_html_e('Keep internal notification log inside ticket meta', 'fluent-cart'); ?></span>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="fc-button fc-button-primary">
                        <?php esc_html_e('Save Notification Settings', 'fluent-cart'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="fc-card fc-card-border">
        <div class="fc-card-header">
            <div class="fc-card-header-left flex-1">
                <h2 class="fc-card-header-title"><?php esc_html_e('AI Assistant', 'fluent-cart'); ?></h2>
                <p class="text-sm text-system-mid mt-1"><?php esc_html_e('Provide an OpenAI API key to enable automated reply suggestions when handling tickets.', 'fluent-cart'); ?></p>
            </div>
        </div>
        <div class="fc-card-body">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-4">
                <?php wp_nonce_field('fluentcart_support_ai'); ?>
                <input type="hidden" name="action" value="fluentcart_support_save_ai" />

                <div class="fc-form-group">
                    <label class="fc-label" for="fluentcart-ai-api-key"><?php esc_html_e('OpenAI API Key', 'fluent-cart'); ?></label>
                    <input type="password" name="ai_api_key" id="fluentcart-ai-api-key" class="fc-input text-sm w-full bg-white !shadow-none rounded border border-solid border-gray-outline py-2 px-3.5 m-0 leading-[1.43] placeholder:text-system-light placeholder:text-[13px] focus:border-primary-500" value="<?php echo esc_attr($aiKey); ?>" placeholder="sk-..." />
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="fc-button fc-button-primary">
                        <?php esc_html_e('Save AI Settings', 'fluent-cart'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
