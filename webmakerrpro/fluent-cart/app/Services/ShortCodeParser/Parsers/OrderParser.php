<?php

namespace FluentCart\App\Services\ShortCodeParser\Parsers;

use FluentCart\Api\CurrencySettings;
use FluentCart\Api\StoreSettings;
use FluentCart\App\Helpers\Helper;
use FluentCart\App\Helpers\Status;
use FluentCart\App\Models\Order;
use FluentCart\App\Models\Subscription;
use FluentCart\App\Services\DateTime\DateTime;
use FluentCart\App\Services\Payments\PaymentReceipt;
use FluentCart\App\Services\TemplateService;
use FluentCart\Framework\Support\Arr;
use FluentCart\Framework\Support\Str;
use FluentCartPro\App\Modules\Licensing\Models\License;

class OrderParser extends BaseParser
{
    private StoreSettings $storeSettings;
    private $order;
    private $orderTz;

    private $licenses;

    private bool $licenseLoaded = false;

    private $subscriptions;

    private bool $subscriptionLoaded = false;

    public function __construct($data)
    {
        $this->storeSettings = new StoreSettings();
        $this->order = Arr::get($data, 'order');
        $config = Arr::wrap(
            Arr::get($this->order, 'config')
        );
        $this->orderTz = Arr::get($config, 'user_tz', 'UTC');
        $orderId = Arr::get($this->order, 'id');


        parent::__construct($data);
    }


//    protected array $methodMap = [
//        'customer_dashboard_link' => 'getCustomerDashboardLink',
//        'payment_summary' => 'getPaymentSummary',
//        'payment_receipt' => 'getPaymentReceipt',
//    ];

    protected array $attributeMap = [
        'id'         => 'order.id',
        'status'     => 'order.status',
        'created_at' => 'order.created_at',
        'updated_at' => 'order.updated_at',
    ];

    protected array $centColumns = [
        'total_amount',
        'subtotal',
        'discount_tax',
        'manual_discount_total',
        'coupon_discount_total',
        'shipping_tax',
        'shipping_total',
        'tax_total',
        'total_paid',
        'discount_total',
        'total_refund'
    ];

    public function parse($accessor = '', $code = '', $transformer = null): ?string
    {


        if ($this->shouldParseAddress($accessor)) {
            return $this->parseAddressFields($accessor);
        }

        if (in_array($accessor, ['updated_at', 'created_at'])) {
            $date = Arr::get($this->data, $this->attributeMap[$accessor]);
            return DateTime::gmtToTimezone($date, $this->orderTz)->format('M d, Y');

        }

        if (in_array($accessor, $this->centColumns)) {
            $amount = Arr::get($this->order, $accessor);
            if (!is_numeric($amount)) {
                return $amount;
            }
            return CurrencySettings::getPriceHtml($amount, $this->order['currency']);
        }


        return $this->get($accessor, $code);
    }

    public function shouldParseAddress($accessor): bool
    {
        return Str::startsWith($accessor, 'billing.') || Str::startsWith($accessor, 'shipping.');
    }

    public function parseAddressFields($accessor)
    {
        list($addressType, $accessorsKey) = $this->resolveAddressFieldKeys($accessor);
        return $this->getAddressData($addressType, $accessorsKey);
    }

    public function resolveAddressFieldKeys($accessor): array
    {
        $exploded = explode('.', $accessor);
        $addressType = $exploded[0];
        $accessorsKey = implode('.', array_slice($exploded, 1));
        return [$addressType, $accessorsKey];
    }

    public function getAddressData($addressAccessor, $accessor = null)
    {
        $address = Arr::get($this->order, $addressAccessor . '_address');

        if (empty($address)) {
            return "";
        }
        return Arr::get($address, $accessor) ?: '';
    }

    public function getPaymentSummary()
    {
        $order = $this->order;
        $paymentReceipt = new PaymentReceipt($this->order);
        $showQuantityColumn = false;

        // Check if any item in the receipt is not a subscription.
        // If such an item exists, set $showQuantityColumn to true.
        foreach ($paymentReceipt->getItems() as $item) {
            if ($item['payment_type'] !== 'subscription') {
                $showQuantityColumn = true;
                break;
            }
        }
        $shop = Helper::shopConfig();
        $currencySign = $shop['currency_sign'];

        ob_start();
        do_action('fluent_cart/views/checkout_order_summary', compact('order', 'paymentReceipt', 'showQuantityColumn', 'currencySign'));
        return ob_get_clean();
    }


    public function getPaymentReceipt()
    {
        $order = $this->order;
        $paymentReceipt = new PaymentReceipt($this->order);
        ob_start();
        do_action('fluent_cart/views/checkout_order_receipt', [
            'order'          => $order,
            'paymentReceipt' => $paymentReceipt
        ]);
        return ob_get_clean();
    }

    public function getCustomerDashboardAnchorLink($accessor, $code = null, $conditions = [])
    {
        $defaultValue = Arr::get($conditions, 'default_value') ?? Arr::get($this->order, 'invoice_no');
        if (empty($this->order)) {
            return $code;
        }

        $profilePage = $this->storeSettings->getCustomerProfilePage();


        if (!empty($profilePage)) {
            return "<a style='color: #017EF3; text-decoration: none;' href='" . "$profilePage#/order/" . Arr::get($this->order, 'uuid') . "'>" . $defaultValue . "</a>";
        } else {
            return Arr::get($this->order, 'invoice_no');
        }

    }

    public function getCustomerDashboardLink($accessor, $code = null)
    {
        if (empty($this->order)) {
            return $code;
        }

        $orderLink = TemplateService::getCustomerProfileUrl('order/' . Arr::get($this->order, 'uuid'));

        return is_user_logged_in() ? $orderLink : wp_login_url($orderLink);
    }

    public function getAdminOrderLink($accessor, $code = null)
    {
        if (empty($this->order)) {
            return $code;
        }
        return admin_url('admin.php?page=fluent-cart#/orders/' . Arr::get($this->order, 'id') . '/view');
    }

    public function getAdminOrderAnchorLink($accessor, $code = null, $conditions = [])
    {
        $defaultValue = Arr::get($conditions, 'default_value');
        if (empty($this->order)) {
            return $code;
        }

        $url = admin_url('admin.php?page=fluent-cart#/orders/' . Arr::get($this->order, 'id') . '/view');

        if (!empty($defaultValue)) {
            return "<a style='color: #017EF3; text-decoration: none;' href='" . $url . "'>" . $defaultValue . "</a>";
        }

        return $url;
    }

    public function getCustomerOrderLink($accessor, $code = null)
    {
        if (empty($this->order)) {
            return $code;
        }

        $customerProfilePage = $this->storeSettings->getCustomerProfilePage();
        $orderLink = $customerProfilePage . '#/order/' . Arr::get($this->order, 'uuid');

        return is_user_logged_in() ? $orderLink : wp_login_url($orderLink);
    }

    public function getTotalAmount()
    {
        $total = ($this->order['total_amount'] / 100);
        $currency_sign = $this->order['currency'];
        return $total . $currency_sign;
    }

    public function getDownloads()
    {
        $order = $this->order;

        $downloads = (new Order())->getDownloadsById($order['id']);

        if(empty($downloads)){
            return '';
        }

        ob_start();
        do_action('fluent_cart/views/order_downloads', [
            'productDownloads' => (new Order())->getDownloadsById($order['id']),
        ]);
        return ob_get_clean();
    }

    public function getLicenses()
    {

        if (!$this->licenseLoaded && class_exists(License::class)) {
            $this->licenses = License::query()
                ->where('order_id', $this->order['id'])
                ->get();
            $this->licenseLoaded = true;
        }


        $licenses = $this->licenses;

        if ($licenses->isEmpty()) {
            return '';
        }
        ob_start();
        do_action('fluent_cart/views/order_licenses', [
            'licenses' => $licenses,
            'order'    => $this->order,
            'orderTz'  => $this->orderTz
        ]);
        return ob_get_clean();
    }

    public function getLicenseCount(): string
    {
        return (string)$this->licenses->count();
    }

    public function getSubscriptions()
    {

        if (!$this->subscriptionLoaded && class_exists(License::class)) {
            $this->subscriptions = Subscription::query()
                ->with('product')
                ->where('parent_order_id', $this->order['id'])
                ->whereNotIn('status', [Status::SUBSCRIPTION_PENDING, Status::SUBSCRIPTION_INTENDED])
                ->get();
            $this->subscriptionLoaded = true;
        }


        $subscriptions = $this->subscriptions;

        if ($subscriptions->isEmpty()) {
            return '';
        }
        ob_start();
        do_action('fluent_cart/views/order_subscriptions', [
            'subscriptions' => $subscriptions->toArray(),
            'order'         => $this->order
        ]);
        return ob_get_clean();
    }

}


