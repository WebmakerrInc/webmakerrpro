<?php
/** @var \FluentCart\Support\Models\Ticket[] $tickets */
/** @var array $inboxes */
?>
<div class="fc-layout-width px-4 py-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center justify-between mb-6">
        <div class="space-y-1">
            <h1 class="text-lg font-semibold text-system-dark dark:text-gray-50"><?php esc_html_e('Support Tickets', 'fluent-cart'); ?></h1>
            <p class="text-sm text-system-mid"><?php esc_html_e('Create and manage customer conversations right inside FluentCart.', 'fluent-cart'); ?></p>
        </div>
    </div>

    <div class="fc-card fc-card-border mb-6">
        <div class="fc-card-header">
            <div class="fc-card-header-left flex-1">
                <h2 class="fc-card-header-title"><?php esc_html_e('Create Ticket', 'fluent-cart'); ?></h2>
                <p class="text-sm text-system-mid mt-1"><?php esc_html_e('Log a support request and assign it to the proper inbox.', 'fluent-cart'); ?></p>
            </div>
        </div>
        <div class="fc-card-body">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-4">
                <?php wp_nonce_field('fluentcart_support_ticket'); ?>
                <input type="hidden" name="action" value="fluentcart_support_create_ticket" />

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="fc-form-group">
                        <label class="fc-label" for="fluentcart-ticket-subject"><?php esc_html_e('Subject', 'fluent-cart'); ?></label>
                        <input type="text" name="subject" id="fluentcart-ticket-subject" class="fc-input text-sm w-full bg-white !shadow-none rounded border border-solid border-gray-outline py-2 px-3.5 m-0 leading-[1.43] placeholder:text-system-light placeholder:text-[13px] focus:border-primary-500" required />
                    </div>

                    <div class="fc-form-group">
                        <label class="fc-label" for="fluentcart-ticket-customer-name"><?php esc_html_e('Customer Name', 'fluent-cart'); ?></label>
                        <input type="text" name="customer_name" id="fluentcart-ticket-customer-name" class="fc-input text-sm w-full bg-white !shadow-none rounded border border-solid border-gray-outline py-2 px-3.5 m-0 leading-[1.43] placeholder:text-system-light placeholder:text-[13px] focus:border-primary-500" required />
                    </div>

                    <div class="fc-form-group">
                        <label class="fc-label" for="fluentcart-ticket-customer-email"><?php esc_html_e('Customer Email', 'fluent-cart'); ?></label>
                        <input type="email" name="customer_email" id="fluentcart-ticket-customer-email" class="fc-input text-sm w-full bg-white !shadow-none rounded border border-solid border-gray-outline py-2 px-3.5 m-0 leading-[1.43] placeholder:text-system-light placeholder:text-[13px] focus:border-primary-500" required />
                    </div>

                    <div class="fc-form-group">
                        <label class="fc-label" for="fluentcart-ticket-inbox"><?php esc_html_e('Inbox', 'fluent-cart'); ?></label>
                        <select name="inbox_id" id="fluentcart-ticket-inbox" class="fc-select fc-input text-sm w-full bg-white !shadow-none rounded border border-solid border-gray-outline py-2 px-3.5 m-0 focus:border-primary-500">
                            <?php foreach ($inboxes as $id => $label) : ?>
                                <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fc-form-group">
                        <label class="fc-label" for="fluentcart-ticket-priority"><?php esc_html_e('Priority', 'fluent-cart'); ?></label>
                        <select name="priority" id="fluentcart-ticket-priority" class="fc-select fc-input text-sm w-full bg-white !shadow-none rounded border border-solid border-gray-outline py-2 px-3.5 m-0 focus:border-primary-500">
                            <option value="low"><?php esc_html_e('Low', 'fluent-cart'); ?></option>
                            <option value="normal" selected><?php esc_html_e('Normal', 'fluent-cart'); ?></option>
                            <option value="high"><?php esc_html_e('High', 'fluent-cart'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="fc-form-group">
                    <label class="fc-label" for="fluentcart-ticket-message"><?php esc_html_e('Message', 'fluent-cart'); ?></label>
                    <textarea name="message" id="fluentcart-ticket-message" rows="5" class="fc-input text-sm w-full bg-white !shadow-none rounded border border-solid border-gray-outline py-2 px-3.5 m-0 leading-[1.6] placeholder:text-system-light placeholder:text-[13px] focus:border-primary-500" required></textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="fc-button fc-button-primary">
                        <?php esc_html_e('Create Ticket', 'fluent-cart'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="fc-card fc-card-border">
        <div class="fc-card-header">
            <div class="fc-card-header-left flex-1">
                <h2 class="fc-card-header-title"><?php esc_html_e('Recent Tickets', 'fluent-cart'); ?></h2>
            </div>
        </div>
        <div class="fc-card-body space-y-4">
            <?php if (!$tickets->count()) : ?>
                <div class="text-sm text-system-mid text-center py-4">
                    <?php esc_html_e('No tickets found.', 'fluent-cart'); ?>
                </div>
            <?php else : ?>
                <?php foreach ($tickets as $ticket) : ?>
                    <div class="border border-solid border-gray-outline rounded-lg bg-white shadow-sm p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <h3 class="text-base font-semibold text-system-dark dark:text-gray-50">
                                <?php echo esc_html(sprintf('#%d %s', $ticket->id, $ticket->subject)); ?>
                            </h3>
                            <span class="text-sm text-system-mid">
                                <?php echo esc_html(get_date_from_gmt($ticket->created_at)); ?>
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-4 text-sm text-system-mid mt-3">
                            <div><span class="font-medium text-system-dark dark:text-gray-50"><?php esc_html_e('Status:', 'fluent-cart'); ?></span> <?php echo esc_html(ucfirst($ticket->status)); ?></div>
                            <div><span class="font-medium text-system-dark dark:text-gray-50"><?php esc_html_e('Inbox:', 'fluent-cart'); ?></span> <?php echo esc_html($ticket->inbox ? $ticket->inbox->title : __('Default Inbox', 'fluent-cart')); ?></div>
                            <div><span class="font-medium text-system-dark dark:text-gray-50"><?php esc_html_e('Customer:', 'fluent-cart'); ?></span> <?php echo esc_html($ticket->customer_name); ?> &lt;<?php echo esc_html($ticket->customer_email); ?>&gt;</div>
                        </div>

                        <?php if ($ticket->replies && $ticket->replies->count()) : ?>
                            <div class="mt-4 border border-solid border-gray-outline rounded-lg bg-gray-50 p-4 space-y-3">
                                <div class="text-sm font-semibold text-system-dark dark:text-gray-50">
                                    <?php esc_html_e('Replies', 'fluent-cart'); ?>
                                </div>
                                <?php foreach ($ticket->replies as $reply) : ?>
                                    <div class="bg-white border border-solid border-gray-outline rounded-md p-3 shadow-sm">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                            <span class="font-medium text-system-dark dark:text-gray-50"><?php echo esc_html($reply->author_name); ?></span>
                                            <span class="text-xs text-system-mid"><?php echo esc_html(get_date_from_gmt($reply->created_at)); ?></span>
                                        </div>
                                        <div class="mt-2 text-sm text-system-dark dark:text-gray-100">
                                            <?php echo wpautop(wp_kses_post($reply->message)); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($ticket->status !== 'closed') : ?>
                            <div class="mt-4 space-y-3">
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-3">
                                    <?php wp_nonce_field('fluentcart_support_ticket'); ?>
                                    <input type="hidden" name="action" value="fluentcart_support_reply_ticket" />
                                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket->id); ?>" />
                                    <textarea name="message" rows="3" class="fc-input text-sm w-full bg-white !shadow-none rounded border border-solid border-gray-outline py-2 px-3.5 m-0 leading-[1.6] placeholder:text-system-light placeholder:text-[13px] focus:border-primary-500" placeholder="<?php esc_attr_e('Write a reply…', 'fluent-cart'); ?>" required></textarea>
                                    <div class="flex justify-end">
                                        <button type="submit" class="fc-button fc-button-secondary">
                                            <?php esc_html_e('Send Reply', 'fluent-cart'); ?>
                                        </button>
                                    </div>
                                </form>

                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="flex justify-end">
                                    <?php wp_nonce_field('fluentcart_support_ticket'); ?>
                                    <input type="hidden" name="action" value="fluentcart_support_close_ticket" />
                                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket->id); ?>" />
                                    <button type="submit" class="fc-button fc-button-info-soft text-red-600" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to close this ticket?', 'fluent-cart')); ?>');">
                                        <?php esc_html_e('Close Ticket', 'fluent-cart'); ?>
                                    </button>
                                </form>
                            </div>
                        <?php else : ?>
                            <p class="mt-4 text-sm text-system-mid italic"><?php esc_html_e('Ticket is closed.', 'fluent-cart'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
