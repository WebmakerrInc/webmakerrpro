<?php
/** @var \FluentCart\Support\Models\Inbox[] $inboxes */
?>
<div class="wrap">
    <h1><?php esc_html_e('Support Inboxes', 'fluent-cart'); ?></h1>

    <h2><?php esc_html_e('Create / Update Inbox', 'fluent-cart'); ?></h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:600px;">
        <?php wp_nonce_field('fluentcart_support_inbox'); ?>
        <input type="hidden" name="action" value="fluentcart_support_save_inbox" />

        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row"><label for="fluentcart-inbox-id"><?php esc_html_e('Inbox ID (leave empty for new)', 'fluent-cart'); ?></label></th>
                <td><input type="number" name="id" id="fluentcart-inbox-id" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="fluentcart-inbox-title"><?php esc_html_e('Name', 'fluent-cart'); ?></label></th>
                <td><input type="text" name="title" id="fluentcart-inbox-title" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="fluentcart-inbox-email"><?php esc_html_e('Email', 'fluent-cart'); ?></label></th>
                <td><input type="email" name="email" id="fluentcart-inbox-email" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Default Inbox', 'fluent-cart'); ?></th>
                <td><label><input type="checkbox" name="is_default" value="1" /> <?php esc_html_e('Set as default inbox', 'fluent-cart'); ?></label></td>
            </tr>
            <tr>
                <th scope="row"><label for="fluentcart-inbox-signature"><?php esc_html_e('Signature', 'fluent-cart'); ?></label></th>
                <td><textarea name="signature" id="fluentcart-inbox-signature" class="large-text" rows="3"></textarea></td>
            </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save Inbox', 'fluent-cart')); ?>
    </form>

    <hr />

    <h2><?php esc_html_e('Existing Inboxes', 'fluent-cart'); ?></h2>
    <?php if (!$inboxes->count()) : ?>
        <p><?php esc_html_e('No inboxes found.', 'fluent-cart'); ?></p>
    <?php else : ?>
        <table class="widefat fixed striped">
            <thead>
            <tr>
                <th><?php esc_html_e('ID', 'fluent-cart'); ?></th>
                <th><?php esc_html_e('Name', 'fluent-cart'); ?></th>
                <th><?php esc_html_e('Email', 'fluent-cart'); ?></th>
                <th><?php esc_html_e('Default', 'fluent-cart'); ?></th>
                <th><?php esc_html_e('Actions', 'fluent-cart'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($inboxes as $inbox) : ?>
                <tr>
                    <td><?php echo esc_html($inbox->id); ?></td>
                    <td><?php echo esc_html($inbox->title); ?></td>
                    <td><?php echo esc_html($inbox->email); ?></td>
                    <td><?php echo $inbox->is_default ? '&#10003;' : ''; ?></td>
                    <td>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                            <?php wp_nonce_field('fluentcart_support_inbox'); ?>
                            <input type="hidden" name="action" value="fluentcart_support_delete_inbox" />
                            <input type="hidden" name="inbox_id" value="<?php echo esc_attr($inbox->id); ?>" />
                            <?php submit_button(__('Delete', 'fluent-cart'), 'delete', 'submit', false, ['onclick' => 'return confirm("' . esc_js(__('Delete this inbox?', 'fluent-cart')) . '");']); ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
