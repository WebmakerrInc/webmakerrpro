<?php
/** @var \FluentCart\Support\Models\Ticket[] $tickets */
/** @var array $inboxes */
?>
<div class="wrap">
    <h1><?php esc_html_e('Support Tickets', 'fluent-cart'); ?></h1>

    <h2 class="title"><?php esc_html_e('Create Ticket', 'fluent-cart'); ?></h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('fluentcart_support_ticket'); ?>
        <input type="hidden" name="action" value="fluentcart_support_create_ticket" />

        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row"><label for="fluentcart-ticket-subject"><?php esc_html_e('Subject', 'fluent-cart'); ?></label></th>
                <td><input type="text" name="subject" id="fluentcart-ticket-subject" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="fluentcart-ticket-customer-name"><?php esc_html_e('Customer Name', 'fluent-cart'); ?></label></th>
                <td><input type="text" name="customer_name" id="fluentcart-ticket-customer-name" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="fluentcart-ticket-customer-email"><?php esc_html_e('Customer Email', 'fluent-cart'); ?></label></th>
                <td><input type="email" name="customer_email" id="fluentcart-ticket-customer-email" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="fluentcart-ticket-inbox"><?php esc_html_e('Inbox', 'fluent-cart'); ?></label></th>
                <td>
                    <select name="inbox_id" id="fluentcart-ticket-inbox" class="regular-text">
                        <?php foreach ($inboxes as $id => $label) : ?>
                            <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fluentcart-ticket-priority"><?php esc_html_e('Priority', 'fluent-cart'); ?></label></th>
                <td>
                    <select name="priority" id="fluentcart-ticket-priority" class="regular-text">
                        <option value="low"><?php esc_html_e('Low', 'fluent-cart'); ?></option>
                        <option value="normal" selected><?php esc_html_e('Normal', 'fluent-cart'); ?></option>
                        <option value="high"><?php esc_html_e('High', 'fluent-cart'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fluentcart-ticket-message"><?php esc_html_e('Message', 'fluent-cart'); ?></label></th>
                <td>
                    <textarea name="message" id="fluentcart-ticket-message" class="large-text" rows="5" required></textarea>
                </td>
            </tr>
            </tbody>
        </table>

        <?php submit_button(__('Create Ticket', 'fluent-cart')); ?>
    </form>

    <hr />

    <h2 class="title"><?php esc_html_e('Recent Tickets', 'fluent-cart'); ?></h2>
    <?php if (!$tickets->count()) : ?>
        <p><?php esc_html_e('No tickets found.', 'fluent-cart'); ?></p>
    <?php else : ?>
        <div class="fluentcart-support-tickets">
            <?php foreach ($tickets as $ticket) : ?>
                <div class="fluentcart-ticket-card" style="background:#fff;border:1px solid #ccd0d4;padding:20px;margin-bottom:20px;">
                    <h3><?php echo esc_html(sprintf('#%d %s', $ticket->id, $ticket->subject)); ?></h3>
                    <p>
                        <strong><?php esc_html_e('Status:', 'fluent-cart'); ?></strong> <?php echo esc_html(ucfirst($ticket->status)); ?><br />
                        <strong><?php esc_html_e('Inbox:', 'fluent-cart'); ?></strong> <?php echo esc_html($ticket->inbox ? $ticket->inbox->title : __('Default Inbox', 'fluent-cart')); ?><br />
                        <strong><?php esc_html_e('Customer:', 'fluent-cart'); ?></strong> <?php echo esc_html($ticket->customer_name); ?> &lt;<?php echo esc_html($ticket->customer_email); ?>&gt;<br />
                        <strong><?php esc_html_e('Created:', 'fluent-cart'); ?></strong> <?php echo esc_html(get_date_from_gmt($ticket->created_at)); ?>
                    </p>

                    <?php if ($ticket->replies && $ticket->replies->count()) : ?>
                        <div class="fluentcart-ticket-replies" style="margin-top:15px;">
                            <strong><?php esc_html_e('Replies', 'fluent-cart'); ?></strong>
                            <ul>
                                <?php foreach ($ticket->replies as $reply) : ?>
                                    <li style="border-top:1px solid #eee;padding-top:10px;margin-top:10px;">
                                        <p>
                                            <strong><?php echo esc_html($reply->author_name); ?></strong>
                                            <em>(<?php echo esc_html(get_date_from_gmt($reply->created_at)); ?>)</em>
                                        </p>
                                        <div><?php echo wpautop(wp_kses_post($reply->message)); ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($ticket->status !== 'closed') : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px;">
                            <?php wp_nonce_field('fluentcart_support_ticket'); ?>
                            <input type="hidden" name="action" value="fluentcart_support_reply_ticket" />
                            <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket->id); ?>" />
                            <textarea name="message" rows="3" class="large-text" placeholder="<?php esc_attr_e('Write a reply…', 'fluent-cart'); ?>" required></textarea>
                            <?php submit_button(__('Send Reply', 'fluent-cart'), 'secondary', 'submit', false); ?>
                        </form>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;">
                            <?php wp_nonce_field('fluentcart_support_ticket'); ?>
                            <input type="hidden" name="action" value="fluentcart_support_close_ticket" />
                            <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket->id); ?>" />
                            <?php submit_button(__('Close Ticket', 'fluent-cart'), 'delete', 'submit', false, ['onclick' => 'return confirm("' . esc_js(__('Are you sure you want to close this ticket?', 'fluent-cart')) . '");']); ?>
                        </form>
                    <?php else : ?>
                        <p><em><?php esc_html_e('Ticket is closed.', 'fluent-cart'); ?></em></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
