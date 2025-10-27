<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
/**
 * @var $order \FluentCart\App\Models\Order
 */
?>

    <div class="space_bottom_30">
        <p>Hello <?php echo esc_html($order->customer->full_name); ?>,</p>
        <p>We have processed a refund for your recent order <a href="<?php echo $order->getViewUrl('customer'); ?>">#<?php echo esc_html($order->invoice_no); ?></a>. Thank you for your understanding, and we truly value your trust in us. Below are the details of your refund.</p>
    </div>

<?php

\FluentCart\App\App::make('view')->render('emails.parts.items_table', [
    'order'          => $order,
    'formattedItems' => $order->order_items,
    'heading'        => 'Summary',
]);

echo '<hr />';

\FluentCart\App\App::make('view')->render('emails.parts.call_to_action_box', [
    'content'     => 'The refund should appear in your account within 5-10 business days, depending on your payment provider.',
    'link'        => $order->getViewUrl('customer'),
    'button_text' => 'View Details'
]);
