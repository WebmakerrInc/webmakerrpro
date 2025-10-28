<?php
/** @var array<int, array<string, mixed>> $tickets */
/** @var array<string, mixed>|null $currentTicket */
?>
<div class="wrap fluent-suite-support">
    <h1><?php esc_html_e('Fluent Support Desk', 'fluent-suite'); ?></h1>

    <div class="fluent-suite-support__layout">
        <div class="fluent-suite-support__tickets">
            <h2><?php esc_html_e('Tickets', 'fluent-suite'); ?></h2>
            <ul>
                <?php foreach ($tickets as $ticket) : ?>
                    <li class="<?php echo (!empty($currentTicket['id']) && (int) $currentTicket['id'] === (int) $ticket['id']) ? 'is-active' : ''; ?>">
                        <a href="<?php echo esc_url(add_query_arg([
                            'page'      => 'fluent-suite-support',
                            'ticket_id' => (int) $ticket['id'],
                        ], admin_url('admin.php'))); ?>">
                            <strong><?php echo esc_html($ticket['subject']); ?></strong><br>
                            <span class="meta">
                                <?php echo esc_html($ticket['customer_email']); ?> ·
                                <?php echo esc_html(ucfirst($ticket['status'])); ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="fluent-suite-support__conversation">
            <?php if ($currentTicket) : ?>
                <h2><?php echo esc_html($currentTicket['subject']); ?></h2>
                <div class="conversation-meta">
                    <span><?php echo esc_html($currentTicket['customer_email']); ?></span>
                    <span><?php echo esc_html(ucfirst($currentTicket['status'])); ?></span>
                    <span><?php echo esc_html($currentTicket['updated_at']); ?></span>
                </div>

                <div class="conversation-thread">
                    <?php foreach ($currentTicket['replies'] as $reply) : ?>
                        <div class="reply reply--<?php echo esc_attr($reply['author_type']); ?>">
                            <div class="reply__meta">
                                <strong><?php echo esc_html($reply['author']); ?></strong>
                                <span><?php echo esc_html($reply['created_at']); ?></span>
                            </div>
                            <div class="reply__message">
                                <?php echo wpautop(wp_kses_post($reply['message'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="post" class="fluent-suite-support__reply">
                    <?php wp_nonce_field('fluent_suite_support_action'); ?>
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr($currentTicket['id']); ?>" />
                    <input type="hidden" name="fluent_suite_support_action" value="reply" />
                    <h3><?php esc_html_e('Add Reply', 'fluent-suite'); ?></h3>
                    <textarea name="reply_message" rows="6" class="large-text" required></textarea>
                    <?php submit_button(__('Send Reply', 'fluent-suite'), 'primary', 'submit', false); ?>
                </form>

                <form method="post" class="fluent-suite-support__status">
                    <?php wp_nonce_field('fluent_suite_support_action'); ?>
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr($currentTicket['id']); ?>" />
                    <input type="hidden" name="fluent_suite_support_action" value="status" />
                    <h3><?php esc_html_e('Update Status', 'fluent-suite'); ?></h3>
                    <select name="ticket_status">
                        <option value="open" <?php selected($currentTicket['status'], 'open'); ?>><?php esc_html_e('Open', 'fluent-suite'); ?></option>
                        <option value="responded" <?php selected($currentTicket['status'], 'responded'); ?>><?php esc_html_e('Responded', 'fluent-suite'); ?></option>
                        <option value="closed" <?php selected($currentTicket['status'], 'closed'); ?>><?php esc_html_e('Closed', 'fluent-suite'); ?></option>
                    </select>
                    <?php submit_button(__('Save Status', 'fluent-suite'), 'secondary', 'submit', false); ?>
                </form>
            <?php else : ?>
                <p><?php esc_html_e('Select a ticket to view the conversation.', 'fluent-suite'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
