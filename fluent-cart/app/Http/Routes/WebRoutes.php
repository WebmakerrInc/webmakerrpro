<?php

namespace FluentCart\App\Http\Routes;

use FluentCart\Api\Resource\FrontendResource\CartResource;
use FluentCart\Api\StoreSettings;
use FluentCart\App\App;

use FluentCart\App\Hooks\Handlers\ShortCodes\Checkout\CheckoutPageHandler;
use FluentCart\App\Hooks\Handlers\ShortCodes\ReceiptHandler;
use FluentCart\App\Http\Controllers\WebController\FileDownloader;
use FluentCart\App\Models\ProductVariation;
use FluentCart\App\Modules\PaymentMethods\PayPalGateway\API\PayPalPartnerRenderer;
use FluentCart\App\Services\FrontendView;
use FluentCart\App\Services\PrintService;
use FluentCart\App\Services\Renderer\CartRenderer;
use FluentCart\App\Services\URL;

class WebRoutes
{
    public static function register()
    {
        add_action('init', function () {
            self::registerRoutes();
        }, 99);

    }

    public static function registerRoutes()
    {
        if (!isset($_REQUEST['fluent-cart']) || !$_REQUEST['fluent-cart']) {
            return;
        }


        $page = sanitize_text_field($_REQUEST['fluent-cart']);

        if ($page === 'instant_checkout') {

            $variationId = App::request()->get('item_id');
            $quantity = App::request()->get('quantity', 1);

            if (!is_numeric($variationId)) {
                return;
            }


            if (is_numeric($quantity)) {
                $quantity = intval($quantity);
                $quantity = max($quantity, 1);
            } else {
                $quantity = 1;
            }


            $variation = ProductVariation::query()->find($variationId);

            if (empty($variation)) {
                return;
            }

            $soldIndividually = $variation->soldIndividually();

            if ($soldIndividually) {
                $quantity = 1;
            }

            $cart = CartResource::generateCartForInstantCheckout($variationId, $quantity);


            if (is_wp_error($cart)) {
                (new CheckoutPageHandler())->enqueueStyles();
                ob_start();
                (new CartRenderer([]))->renderEmptyCart();
                $view = ob_get_clean();
                FrontendView::make(__('Product Not Found', 'fluent-cart'), $view);
                die();
            }

            $coupons = App::request()->get('coupons', '');
            if ($coupons) {
                $coupons = explode(',', $coupons);
                $coupons = array_map('sanitize_text_field', $coupons);
                $cart->applyCoupon($coupons);
            }

            $target_path = (new StoreSettings())->getCheckoutPage();

            if (isset($_REQUEST['redirect_to'])) {
                $url = sanitize_url($_REQUEST['redirect_to']);
                if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                    $target_path = $url;
                }
            }


            // Step 1: Get current query string and parse to array
            parse_str($_SERVER['QUERY_STRING'], $queryArray);

            // Step 2: Build the new query string from the array
            $queryString = http_build_query($queryArray);

            unset($queryArray['fluent-cart']);
            unset($queryArray['item_id']);
            unset($queryArray['quantity']);
            unset($queryArray['coupons']);
            unset($queryArray['redirect_to']);

            $queryArray['fct_cart_hash'] = $cart->cart_hash;


            $redirect_url = URL::appendQueryParams($target_path, $queryArray);


            wp_safe_redirect($redirect_url);
            exit;
        }

        $served = self::handleMainRoutes($page);

        // Handle faker routes if enabled and not already served
        if (!$served && App::config()->get('using_faker') === true) {
            $served = FakerRoutes::handle($page);
        }

        $actionName = sanitize_text_field($_REQUEST['fluent-cart']);

        if (has_action('fluent_cart_action_' . $actionName)) {
            do_action('fluent_cart_action_' . $actionName, $_REQUEST);
            die();
        }

        if ($served) {
            die();
        }

        return '';
    }

    private
    static function handleMainRoutes($page): bool
    {
        $request = App::request();
        switch ($page) {
            case 'fluent_cart_payment_authenticate':
                (new PayPalPartnerRenderer($request->mode))->render(
                    $request->all()
                );
                break;
            case 'download-by-id':
            case 'download-file':
                echo (new FileDownloader())->index(App::request());
                return true;

            case 'receipt':
                $receiptHandler = new ReceiptHandler();

                $view = $receiptHandler->renderRedirectPage([
                    'type' => 'receipt'
                ]);

                FrontendView::make(
                    __('Order Receipt', 'fluent-cart'),
                    $view,
                    [
                        'styles'         => [
                            'public/checkout/style/confirmation.scss'
                        ],
                        'scripts'        => [
                            [
                                'source'   => 'public/lib/printThis-2.0.0.min.js',
                                'isStatic' => true
                            ],
                            'public/print/Print.js'
                        ],
                        'enqueue_prefix' => 'fluent-cart-checkout-order-receipt',
                        'wp_head'        => false,
                        'wp_footer'      => false,
                    ]);
                return true;


            case 'block-render':
                self::handleBlockRender();
                return true;

            case 'print-invoice':
                return self::handlePrintRoute('invoice');

            case 'print-packing-slip':
                return self::handlePrintRoute('packingSlip');

            case 'print-delivery-slip':
                return self::handlePrintRoute('deliverySlip');

            case 'print-shipping-slip':
                return self::handlePrintRoute('shippingSlip');

            case 'print-dispatch-slip':
                return self::handlePrintRoute('dispatchSlip');

            default:
                return false;
        }
    }

    private
    static function handleBlockRender()
    {
        $block = App::request()->get('block');
        $attributes = App::request()->get('attributes');

        $blockMarkup = '<!-- wp:' . $block;
        if (!empty($attributes)) {
            $blockMarkup .= ' ' . json_encode($attributes);
        }
        $blockMarkup .= ' /-->';

        FrontendView::makeForBlock("", $blockMarkup);

    }

    private
    static function handlePrintRoute($method): bool
    {
        if (!empty($_GET['order'])) {
            PrintService::$method($_GET['order']);
            return true;
        }
        return false;
    }
}
