<?php

namespace FluentSupport\App\Services\Integrations\FluentCart;
use FluentCart\App\Models\Order;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;
use FluentSupport\App\Models\Customer;

class FluentCart
{
    public function boot()
    {
//        add_filter('fluent_support/customer_extra_widgets', array($this, 'getFluentCartPurchaseWidgets'), 120, 2);
        // add_filter('fluent_support/customer_extra_widgets', array($this, 'getFluentCartProLicenseWidget'), 125, 2);
        add_action('fluent_cart/order_created', [$this, 'addCustomer'], 10, 1);
        if (!apply_filters('fluent_support/disable_fc_menu', false)) {
            $this->renderCustomerPortalInFluentCartDashboard();
        }
    }

    public function getFluentCartPurchaseWidgets($widgets, $customer)
    {
        $wpUserId = $customer->user_id;

        // Get all order items for this customer, grouped by product
        $orderItems = \FluentCart\App\Models\OrderItem::whereHas('order.customer', function ($query) use ($wpUserId) {
            $query->where('user_id', $wpUserId);
        })
            ->with([
                'order' => function ($query) {
                    $query->select(['id', 'status', 'created_at', 'total_amount', 'currency', 'invoice_no']);
                }
            ])
            ->select(['id', 'order_id', 'post_id', 'post_title', 'title', 'payment_type', 'unit_price', 'subtotal', 'quantity'])
            ->orderByDesc('id')
            ->get();

        if ($orderItems->isEmpty()) {
            return $widgets;
        }

        // Create individual product entries for each order item (no grouping)
        $formattedProducts = [];

        foreach ($orderItems as $item) {
            $licenseType = $this->getLicenseType($item);

            $formattedProducts[] = [
                'product_name' => $item->post_title,
                'variation_name' => $item->title !== $item->post_title ? $item->title : null,
                'license_type' => $licenseType,
                'price' => $item->unit_price,
                'currency' => $item->order->currency,
                'formatted_price' => $item->formatted_total,
                'status' => $this->getProductStatus($item),
                'order' => [
                    'id' => $item->order->id,
                    'invoice_no' => $item->order->invoice_no,
                    'status' => $item->order->status,
                    'date' => $item->order->created_at->format('Y-m-d H:i:s'),
                    'currency' => $item->order->currency,
                    'total' => number_format($item->order->total_amount / 100, 2, '.', ''),
                    'quantity' => $item->quantity
                ]
            ];
        }

        // Sort products by most recent order date
        usort($formattedProducts, function ($a, $b) {
            return strtotime($b['order']['date']) - strtotime($a['order']['date']);
        });

        // Get FluentCart customer ID
        $fluentCartCustomer = \FluentCart\App\Models\Customer::where('user_id', $wpUserId)->first();
        if (!$fluentCartCustomer) {
            return $widgets;
        }

        $widgets['fct_purchases'] = [
            'title'  => __('Fluent Cart Purchases', 'fluent-support'),
            'products' => $formattedProducts,
            'fluent_cart_customer_id' => $fluentCartCustomer->id,
            'summary' => [
                'lifetime_value' => \FluentCart\App\Helpers\Helper::toDecimal($fluentCartCustomer->ltv),
                'currency' => Arr::get($formattedProducts, '0.currency', \FluentCart\Api\CurrencySettings::get('currency')),
                'total_purchases' => $fluentCartCustomer->purchase_count,
                'first_purchase' => $fluentCartCustomer->first_purchase_date ? date('F j, Y', strtotime($fluentCartCustomer->first_purchase_date)) : null,
                'last_purchase' => $fluentCartCustomer->last_purchase_date ? date('F j, Y', strtotime($fluentCartCustomer->last_purchase_date)) : null,
            ]
        ];

        return $widgets;
    }

    // public function getFluentCartProLicenseWidget($widgets, $customer)
    // {
    //     // Add customer's product licenses if available
    //     $licenses = $this->getCustomerProductLicenses($customer);

    //     if ($licenses) {
    //         $widgets['fct_license'] = [
    //             'header' => __('Fluent Cart Product Licenses', 'fluent-support'),
    //             'licenses' => $licenses
    //         ];
    //     }

    //     return $widgets;
    // }

    public function addCustomer($param)
    {
        $fluentCartCustomer = Arr::get($param, 'customer');

        if (empty($fluentCartCustomer['email'])) {
            return;
        }

        $customerData = [
            'email' => $fluentCartCustomer['email'],
            'first_name' => $fluentCartCustomer['first_name'] ?? '',
            'last_name' => $fluentCartCustomer['last_name'] ?? '',
            'status' => 'active'
        ];

        // Add user_id if available
        if (!empty($fluentCartCustomer['user_id'])) {
            $customerData['user_id'] = $fluentCartCustomer['user_id'];
        }

        // Address field mappings for efficient processing
        $addressMappings = [
            'city' => 'city',
            'state' => 'state',
            'country' => 'country',
            'postcode' => 'zip'
        ];

        // Process primary address fields
        foreach ($addressMappings as $source => $target) {
            if (!empty($fluentCartCustomer[$source])) {
                $customerData[$target] = $fluentCartCustomer[$source];
            }
        }

        // Use billing address as fallback and add address lines
        $billingAddress = $fluentCartCustomer['primary_billing_address'] ?? [];
        if (!empty($billingAddress)) {
            foreach ($addressMappings as $source => $target) {
                if (empty($customerData[$target]) && !empty($billingAddress[$source])) {
                    $customerData[$target] = $billingAddress[$source];
                }
            }

            foreach (['address_1' => 'address_line_1', 'address_2' => 'address_line_2'] as $source => $target) {
                if (!empty($billingAddress[$source])) {
                    $customerData[$target] = $billingAddress[$source];
                }
            }
        }

        Customer::maybeCreateCustomer($customerData);
    }

    /**
     * Get customer's product licenses from FluentCart Pro
     *
     * @param Customer $customer
     * @return array|null Structured license data or null if not available
     */
    private function getCustomerProductLicenses($customer)
    {
        // Check if FluentCart Pro License model is available
        if (!class_exists('\FluentCartPro\App\Modules\Licensing\Models\License')) {
            return null;
        }

        try {
            $wpUserId = $customer->user_id;
            if (!$wpUserId) {
                return null;
            }

            // Get FluentCart customer ID
            $fluentCartCustomer = \FluentCart\App\Models\Customer::where('user_id', $wpUserId)->first();
            if (!$fluentCartCustomer) {
                return null;
            }

            // Get licenses for this customer
            $licenses = \FluentCartPro\App\Modules\Licensing\Models\License::where('customer_id', $fluentCartCustomer->id)
                ->with(['product'])
                ->orderByDesc('created_at')
                ->get()
                ->toArray();

            if (empty($licenses)) {
                return null;
            }

            $formattedLicenses = [];

            foreach ($licenses as $license) {
                // Get product name from the data structure
                $productName = 'Unknown Product';
                if (isset($license['product']['post_title'])) {
                    $productName = $license['product']['post_title'];
                }



                $status = $license['status'];
                $expirationDate = $license['expiration_date'];

                // Format expiration date for tooltip
                $expirationTooltip = '';
                if ($expirationDate) {
                    $expirationTooltip = 'Expires: ' . date('M d, Y', strtotime($expirationDate));
                } else {
                    $expirationTooltip = 'Expires: Lifetime';
                }

                $formattedLicenses[] = [
                    'id' => $license['id'],
                    'product_name' => $productName,
                    'status' => $status,
                    'expiration_tooltip' => $expirationTooltip,
                    'expiration_date' => $expirationDate
                ];
            }

            return $formattedLicenses;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Determine the license type based on order item payment type
     *
     * @param \FluentCart\App\Models\OrderItem $item
     * @return string|null
     */
    private function getLicenseType($item)
    {
        switch ($item->payment_type) {
            case 'subscription':
                // Return 'Subscription' for internal logic but don't display it as license type
                return 'Subscription';
            case 'payment':
            default:
                // Check if it's a lifetime license based on product name or other criteria
                if (stripos($item->post_title, 'lifetime') !== false || stripos($item->title, 'lifetime') !== false) {
                    return 'Lifetime License';
                }
                // Don't show license type for regular one-time purchases
                return null;
        }
    }

    /**
     * Determine the product status based on order status and payment type
     *
     * @param \FluentCart\App\Models\OrderItem $item
     * @return string
     */
    private function getProductStatus($item)
    {
        $orderStatus = $item->order->status;

        // Return 'canceled' for multiple statuses
        if (in_array($orderStatus, ['canceled', 'failed'])) {
            return 'canceled';
        }

        // Return default statuses
        $statusMap = [
            'completed' => 'completed',
            'processing' => 'processing',
            'on-hold' => 'on-hold'
        ];

        return $statusMap[$orderStatus] ?? 'pending'; // Default to 'pending' if not found
    }

    private function renderCustomerPortalInFluentCartDashboard()
    {
        if (!function_exists('fluent_cart_api') || Helper::getBusinessSettings('enable_fc_menu') != 'yes' ) {
            return;
        }

        fluent_cart_api()->addCustomerDashboardEndpoint('fluent-support', [
            'title'           => __('Support', 'fluent-support'),
            'render_callback' => function () {
                echo do_shortcode('[fluent_support_portal]');
            },
            'priority'        => 'high',
            'page_id_x'       => apply_filters('fluent_support/fc_menu_page_id', 573),
        ]);
    }
}
