<?php

namespace FluentCart\App\Hooks\Handlers\BlockEditors;

use FluentCart\App\Services\Renderer\ProductRenderer;
use FluentCart\App\Services\Translations\TransStrings;
use FluentCart\App\Vite;
use FluentCart\Framework\Support\Arr;
use FluentCart\App\Models\Product;
use FluentCart\App\Helpers\Helper;
use FluentCart\App\App;
use FluentCart\Api\StoreSettings;

class BuySectionBlockEditor extends BlockEditor
{
    protected static string $editorName = 'buy-section';

    protected function getScripts(): array
    {
        return [
            [
                'source'       => 'admin/BlockEditor/BuySection/BuySectionBlockEditor.jsx',
                'dependencies' => ['wp-blocks', 'wp-components']
            ]
        ];
    }

    protected function getStyles(): array
    {
        return [
            'admin/BlockEditor/BuySection/style/buy-section-block-editor.scss'
        ];
    }

    protected function localizeData(): array
    {
        return [
            $this->getLocalizationKey()     => [
                'slug'              => $this->slugPrefix,
                'name'              => static::getEditorName(),
                'title'             => __('Buy Section', 'fluent-cart'),
                'description'       => __('This block will display the buy section.', 'fluent-cart'),
                'placeholder_image' => Vite::getAssetUrl('images/placeholder.svg'),
            ],
            'fluent_cart_block_translation' => TransStrings::blockStrings(),
        ];
    }

    public function render(array $shortCodeAttribute, $block = null)
    {
       
        $product = null;
        $insideProductInfo = Arr::get($shortCodeAttribute, 'inside_product_info', 'no');
        $queryType = Arr::get($shortCodeAttribute, 'query_type', 'default');

        if ($insideProductInfo === 'yes' || $queryType === 'default') {
            $product = fluent_cart_get_current_product();
        } else {
            $productId = Arr::get($shortCodeAttribute, 'product_id', false);
            if ($productId) {
                $product = Product::query()->with(['variants'])->find($productId);
            }
        }



        if (!$product) {
            return '';
        }

        wp_enqueue_style(
            'fluentcart-single-product',
            Vite::getAssetUrl('public/single-product/single-product.scss'),
            [],
            ''
        );


        Vite::enqueueStyle(
            'fluentcart-add-to-cart-btn-css',
            'public/buttons/add-to-cart/style/style.scss'
        );
        Vite::enqueueStyle(
            'fluentcart-direct-checkout-btn-css',
            'public/buttons/direct-checkout/style/style.scss'
        );

        Vite::enqueueScript(
            'fluentcart-single-product-js',
            'public/single-product/SingleProduct.js',
            []
        )->with([
            'fluentcart_single_product_vars' => [
                'trans'                    => TransStrings::singleProductPageString(),
                'cart_button_text'         => __('Add To Cart', 'fluent-cart'),
                // App::storeSettings()->get('cart_button_text', ),
                'out_of_stock_button_text' => App::storeSettings()->get('out_of_stock_button_text', __('Out of Stock', 'fluent-cart')),
                'in_stock_status'          => Helper::IN_STOCK,
                'out_of_stock_status'      => Helper::OUT_OF_STOCK,
                'enable_image_zoom'        => (new StoreSettings())->get('enable_image_zoom_in_single_product')
            ]
        ]);

        ob_start();
        (new ProductRenderer($product))->renderBuySection();
        return ob_get_clean();
    }
}
