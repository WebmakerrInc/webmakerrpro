<?php
/** @var \FluentCart\Support\Models\Inbox[] $inboxes */
/** @var string|null $adminMenuHtml */
?>
<div class="fluent-cart-admin-pages fc-support-admin-page">
    <?php if (!empty($adminMenuHtml)) : ?>
        <?php echo $adminMenuHtml; ?>
    <?php endif; ?>

    <div class="fc-layout-width px-4 py-6 space-y-6">
        <div class="space-y-1">
            <h1 class="text-lg font-semibold text-system-dark dark:text-gray-50"><?php esc_html_e('Support Inboxes', 'fluent-cart'); ?></h1>
            <p class="text-sm text-system-mid"><?php esc_html_e('Manage the mailboxes that route customer tickets into FluentCart.', 'fluent-cart'); ?></p>
        </div>

    <div class="fc-card fc-card-border">
        <div class="fc-card-header">
            <div class="fc-card-header-left flex-1">
                <h2 class="fc-card-header-title"><?php esc_html_e('Create / Update Inbox', 'fluent-cart'); ?></h2>
                <p class="text-sm text-system-mid mt-1"><?php esc_html_e('Update inbox details or add a new address for support requests.', 'fluent-cart'); ?></p>
            </div>
        </div>
        <div class="fc-card-body">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-4">
                <?php wp_nonce_field('fluentcart_support_inbox'); ?>
                <input type="hidden" name="action" value="fluentcart_support_save_inbox" />

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="fc-form-group">
                        <label class="fc-label" for="fluentcart-inbox-id"><?php esc_html_e('Inbox ID (leave empty for new)', 'fluent-cart'); ?></label>
                        <input type="number" name="id" id="fluentcart-inbox-id" class="fc-input text-sm w-full bg-white !shadow-none rounded border border-solid border-gray-outline py-2 px-3.5 m-0 leading-[1.43] placeholder:text-system-light placeholder:text-[13px] focus:border-primary-500" />
                    </div>

                    <div class="fc-form-group">
                        <label class="fc-label" for="fluentcart-inbox-title"><?php esc_html_e('Name', 'fluent-cart'); ?></label>
                        <input type="text" name="title" id="fluentcart-inbox-title" class="fc-input text-sm w-full bg-white !shadow-none rounded border border-solid border-gray-outline py-2 px-3.5 m-0 leading-[1.43] placeholder:text-system-light placeholder:text-[13px] focus:border-primary-500" required />
                    </div>

                    <div class="fc-form-group">
                        <label class="fc-label" for="fluentcart-inbox-email"><?php esc_html_e('Email', 'fluent-cart'); ?></label>
                        <input type="email" name="email" id="fluentcart-inbox-email" class="fc-input text-sm w-full bg-white !shadow-none rounded border border-solid border-gray-outline py-2 px-3.5 m-0 leading-[1.43] placeholder:text-system-light placeholder:text-[13px] focus:border-primary-500" required />
                    </div>

                    <div class="fc-form-group">
                        <label class="fc-label"><?php esc_html_e('Default Inbox', 'fluent-cart'); ?></label>
                        <label class="flex items-center gap-3 text-sm text-system-dark dark:text-gray-50">
                            <input type="checkbox" name="is_default" value="1" class="w-4 h-4 text-primary-500 border border-gray-outline rounded" />
                            <span><?php esc_html_e('Set as default inbox', 'fluent-cart'); ?></span>
                        </label>
                    </div>
                </div>

                <div class="fc-form-group">
                    <label class="fc-label" for="fluentcart-inbox-signature"><?php esc_html_e('Signature', 'fluent-cart'); ?></label>
                    <textarea name="signature" id="fluentcart-inbox-signature" class="fc-input text-sm w-full bg-white !shadow-none rounded border border-solid border-gray-outline py-2 px-3.5 m-0 leading-[1.6] placeholder:text-system-light placeholder:text-[13px] focus:border-primary-500" rows="3"></textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="fc-button fc-button-primary">
                        <?php esc_html_e('Save Inbox', 'fluent-cart'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="fc-card fc-card-border">
        <div class="fc-card-header">
            <div class="fc-card-header-left flex-1">
                <h2 class="fc-card-header-title"><?php esc_html_e('Existing Inboxes', 'fluent-cart'); ?></h2>
            </div>
        </div>
        <div class="fc-card-body">
            <?php if (!$inboxes->count()) : ?>
                <div class="text-sm text-system-mid text-center py-4">
                    <?php esc_html_e('No inboxes found.', 'fluent-cart'); ?>
                </div>
            <?php else : ?>
                <div class="overflow-x-auto border border-solid border-gray-outline rounded-lg">
                    <table class="w-full text-sm text-system-dark">
                        <thead class="bg-gray-50">
                        <tr class="text-left text-xs uppercase text-system-mid">
                            <th class="px-4 py-3 font-medium"><?php esc_html_e('ID', 'fluent-cart'); ?></th>
                            <th class="px-4 py-3 font-medium"><?php esc_html_e('Name', 'fluent-cart'); ?></th>
                            <th class="px-4 py-3 font-medium"><?php esc_html_e('Email', 'fluent-cart'); ?></th>
                            <th class="px-4 py-3 font-medium"><?php esc_html_e('Default', 'fluent-cart'); ?></th>
                            <th class="px-4 py-3 font-medium"><?php esc_html_e('Actions', 'fluent-cart'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($inboxes as $inbox) : ?>
                            <tr class="border-t border-gray-outline">
                                <td class="px-4 py-3"><?php echo esc_html($inbox->id); ?></td>
                                <td class="px-4 py-3"><?php echo esc_html($inbox->title); ?></td>
                                <td class="px-4 py-3"><?php echo esc_html($inbox->email); ?></td>
                                <td class="px-4 py-3 text-center"><?php echo $inbox->is_default ? '&#10003;' : ''; ?></td>
                                <td class="px-4 py-3">
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="inline-flex" onsubmit="return confirm('<?php echo esc_js(__('Delete this inbox?', 'fluent-cart')); ?>');">
                                        <?php wp_nonce_field('fluentcart_support_inbox'); ?>
                                        <input type="hidden" name="action" value="fluentcart_support_delete_inbox" />
                                        <input type="hidden" name="inbox_id" value="<?php echo esc_attr($inbox->id); ?>" />
                                        <button type="submit" class="fc-button fc-button-info-soft text-red-600">
                                            <?php esc_html_e('Delete', 'fluent-cart'); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
