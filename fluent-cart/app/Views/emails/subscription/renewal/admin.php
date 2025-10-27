<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
/**
 * @var \FluentCart\App\Models\Subscription $subscription
 * @var \FluentCart\App\Models\Order $order
 */

$transaction = $subscription->getLatestTransaction();
$celebration = \FluentCart\App\Services\TemplateService::getCelebration('renewal');
?>

<?php
if (!empty($celebration)) {
    \FluentCart\App\App::make('view')->render('emails.parts.celebration', [
        'text' => $celebration
    ]);
}
?>

<div class="space_bottom_30">
    <p>Hey There 👋,</p>
    <p><?php echo $subscription->customer->full_name; ?> just renewed their subscription: <b><?php echo esc_html($subscription->item_name); ?></b>.</p>
</div>

<div class="space_bottom_30">
    <p><b>Subscription Renewal Summary:</b></p>
    <p>Renewal Date: <b><?php echo esc_html($transaction->created_at->format('d M Y, H:i')); ?></b></p>
    <p>Renewal Amount 💰: <b><?php echo \FluentCart\App\Helpers\Helper::toDecimal($transaction->total); ?></b></p>
    <p>Payment Method: <b><?php echo $transaction->getPaymentMethodText(); ?></b></p>
    <p>Vendor Transaction ID: <b><?php echo $transaction->vendor_charge_id; ?></b></p>
</div>


<?php
\FluentCart\App\App::make('view')->render('emails.parts.call_to_action_box', [
    'content'     => 'Want to see more details about this reneal? You can view the order details page for more information.',
    'link'        => $transaction->order->getViewUrl('admin'),
    'button_text' => 'View Details',
]);
?>
