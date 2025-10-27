<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
/**
 * @var $order \FluentCart\App\Models\Order
 */
?>

<div class="space_bottom_30">
    <p>Hello <?php echo esc_html($order->customer->full_name); ?>,</p>
    <p>Thank you for purchase! Your order has been successfully placed and confirmed. Here is the details of your
        order.</p>
</div>

<?php

\FluentCart\App\App::make('view')->render('emails.parts.items_table', [
        'order'          => $order,
        'formattedItems' => $order->order_items,
        'heading'        => 'Order Summary',
]);


if($order->subscriptions && $order->subscriptions->count() > 0) {
    \FluentCart\App\App::make('view')->render('invoice.parts.subscription_items', [
        'subscriptions' => $order->subscriptions,
        'order'         => $order
    ]);
}

$licenses = $order->getLicenses();
if ($licenses && $licenses->count() > 0) {
    \FluentCart\App\App::make('view')->render('emails.parts.licenses', [
            'licenses'    => $licenses,
            'heading'     => 'Licenses',
            'show_notice' => false
    ]);
}

$downloads = $order->getDownloads();


if ($downloads) {
    \FluentCart\App\App::make('view')->render('emails.parts.downloads', [
            'order'         => $order,
            'heading'       => 'Downloads',
            'downloadItems' => $order->getDownloads() ?: [],
    ]);
}


echo '<hr />';

\FluentCart\App\App::make('view')->render('emails.parts.addresses', [
        'order' => $order,
]);


\FluentCart\App\App::make('view')->render('emails.parts.call_to_action_box', [
        'content'     => 'To download receipt and view your order details, please visit the order details page.',
        'link'        => $order->getViewUrl('customer'),
        'button_text' => 'View Details'
]);
