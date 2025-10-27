<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
/**
 * @var $order \FluentCart\App\Models\Order
 */
$celebration = \FluentCart\App\Services\TemplateService::getCelebration('order');
?>

<?php
if (!empty($celebration)) {
    \FluentCart\App\App::make('view')->render('emails.parts.celebration', [
        'text' => $celebration
    ]);
}
?>

<div class="space_bottom_30">
    <p>Hey there 🙌,</p>
    <p><?php echo esc_html($order->customer->full_name); ?> just placed an order. Here is the details:</p>
</div>

<?php

\FluentCart\App\App::make('view')->render('emails.parts.items_table', [
    'order'          => $order,
    'formattedItems' => $order->order_items,
    'heading'        => 'Order Summary',
]);

echo '<hr />';

\FluentCart\App\App::make('view')->render('emails.parts.addresses', [
    'order' => $order,
]);

echo '<hr />';

\FluentCart\App\App::make('view')->render('emails.parts.call_to_action_box', [
    'content'     => 'To view more details of this order please check the order detail page.',
    'link'        => $order->getViewUrl('admin'),
    'button_text' => 'View Details'
]);
