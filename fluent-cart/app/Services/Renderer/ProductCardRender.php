<?php

namespace FluentCart\App\Services\Renderer;

use FluentCart\App\Helpers\Helper;
use FluentCart\App\Models\Product;
use FluentCart\App\Modules\Templating\AssetLoader;
use FluentCart\App\Vite;
use FluentCart\Framework\Support\Arr;

class ProductCardRender
{
    protected $product;

    protected $viewUrl = '';

    protected $config = [];

    public function __construct(Product $product, $config = [])
    {
        $this->product = $product;
        $this->viewUrl = $product->view_url;
        $this->config = $config;
    }

    public function renderWrapperStart()
    {

    }

    public function renderWrapperEnd()
    {

    }

    public function render()
    {
        AssetLoader::loadSingleProductAssets();
        $cursor = '';
        if (!empty($this->config['cursor'])) {
            $cursor = 'data-fluent-cart-cursor="' . esc_attr($this->config['cursor']) . '"';
        }

        $cardWidth = '';
        if (Arr::get($this->config, 'card_width', '')) {
            $cardWidth = 'style="width: ' . esc_attr(Arr::get($this->config, 'card_width') . 'px') . ';"';
        }

        ?>
        <article data-fluent-cart-shop-app-single-product data-fc-product-card=""
                 class="fc-product-card"
                <?php echo $cursor; ?>
                <?php echo $cardWidth; ?>
                 aria-label="<?php echo esc_attr(sprintf(__('%s product card', 'fluent-cart'), $this->product->post_title)); ?>">
            <?php $this->renderProductImage(); ?>
            <div class="fc-product-card-content">
                <?php $this->renderTitle(); ?>
                <?php $this->renderExcerpt(); ?>
                <?php $this->renderPrices(); ?>
                <?php $this->showBuyButton(); ?>
            </div>
        </article>
        <?php
    }

    public function renderExcerpt()
    {
        if (empty($this->product->post_excerpt)) {
            return;
        }
        ?>
        <p class="fc-product-card-excerpt">
            <?php echo esc_html($this->product->post_excerpt); ?>
        </p>
        <?php
    }

    public function renderTitle($atts = '')
    {
        echo sprintf(
                '<h3 %1$s class="fc-product-card-title">
                <a data-fluent-cart-product-link 
                   data-product-id="%2$s" 
                   href="%3$s" 
                   aria-label="%6$s">%5$s</a>
            </h3>',
                $atts,
                $this->product->ID,
                esc_url($this->product->view_url),
                esc_attr($this->product->post_title),
                esc_html($this->product->post_title),
                esc_attr(sprintf(__('View details for %s', 'fluent-cart'), $this->product->post_title))
        );
    }

    public function renderProductImage()
    {
        $image = $this->product->thumbnail;
        $isPlaceholder = false;

        if (!$image) {
            $image = Vite::getAssetUrl('images/placeholder.svg');
            $isPlaceholder = true;
        }

        $altText = $isPlaceholder
                ? sprintf(__('Placeholder image for %s', 'fluent-cart'), $this->product->post_title)
                : $this->product->post_title;
        ?>
        <a class="fc-product-card-image-wrap"
           href="<?php echo esc_url($this->viewUrl); ?>"
           style="display: block;"
           aria-label="<?php echo esc_attr(sprintf(__('View %s product image', 'fluent-cart'), $this->product->post_title)); ?>">
            <img class="fc-product-card-image"
                 data-fluent-cart-shop-app-single-product-image
                 src="<?php echo esc_url($image); ?>"
                 alt="<?php echo esc_attr($altText); ?>"
                 loading="lazy"
                 width="300"
                 height="300"/>
        </a>
        <?php
    }

    public function renderPrices($wrapper_attributes = '')
    {
        $priceFormat = Arr::get($this->config, 'price_format', 'starts_from');
        $isSimple = $this->product->detail->variation_type === 'simple';
        $minPrice = $this->product->detail->min_price;
        $maxPrice = $this->product->detail->max_price;
        $comparePrice = 0;

        if ($isSimple) {
            $firstVariant = $this->product->variants->first();
            if ($firstVariant) {
                $minPrice = $firstVariant->item_price;
                if ($firstVariant->compare_price > $minPrice) {
                    $comparePrice = $firstVariant->compare_price;
                }
            }
        }

        $formattedMinPrice = Helper::toDecimal($minPrice);
        $formattedMaxPrice = Helper::toDecimal($maxPrice);
        $formattedComparePrice = Helper::toDecimal($comparePrice);

        do_action('fluent_cart/product/group/before_price_block', [
            'product'       => $this->product,
            'current_price' => $minPrice,
            'scope'         => 'product_card'
        ]);
        ?>
        <div <?php echo $wrapper_attributes; ?>
                class="fc-product-card-prices"
                role="region"
                aria-label="<?php echo esc_attr__('Product pricing', 'fluent-cart'); ?>">
            <?php if ($comparePrice): ?>
                <span class="fc-compare-price" aria-label="<?php echo esc_attr(sprintf(__('Original price: %s', 'fluent-cart'), $formattedComparePrice)); ?>">
                    <del aria-hidden="true"><?php echo esc_html($formattedComparePrice); ?></del>
                </span>
            <?php endif; ?>

            <?php if (!$comparePrice && $maxPrice && $maxPrice > $minPrice): ?>
                <!-- Case 2: price range -->
                <?php if ($priceFormat === 'range'): ?>
                    <span class="fc-item-price" aria-label="<?php echo esc_attr(sprintf(__('Price range from %1$s to %2$s', 'fluent-cart'), $formattedMinPrice, $formattedMaxPrice)); ?>">
                        <span aria-hidden="true"><?php echo esc_html($formattedMinPrice); ?> - <?php echo esc_html($formattedMaxPrice); ?></span>
                    </span>
                <?php else: ?>
                    <span class="fc-item-price" aria-label="<?php echo esc_attr(sprintf(__('Starting from %s', 'fluent-cart'), $formattedMinPrice)); ?>">
                        <span aria-hidden="true"><?php printf(esc_html__('From %s', 'fluent-cart'), esc_html($formattedMinPrice)); ?></span>
                    </span>
                <?php endif; ?>

            <?php else: ?>
                <!-- Case 3: Simple or single price -->
                <span class="fc-item-price" aria-label="<?php echo esc_attr(sprintf(__('Price: %s', 'fluent-cart'), $formattedMinPrice)); ?>">
                    <span aria-hidden="true"><?php echo esc_html($formattedMinPrice); ?></span>
                </span>
            <?php endif; ?>

            <?php do_action('fluent_cart/product/after_price', [
                    'product'       => $this->product,
                    'current_price' => $minPrice,
                    'scope'         => 'product_card'
            ]); ?>
        </div>
        <?php
         do_action('fluent_cart/product/group/after_price_block', [
                'product'       => $this->product,
                'current_price' => $minPrice,
                'scope'         => 'product_card'
        ]);
    }

    /*
     * @todo: Implement Stock Check
     */
    public function showBuyButton($atts = [])
    {
        $isSimple = $this->product->detail->variation_type === 'simple';
        $firstVariant = null;
        $buttonHref = $this->viewUrl;

        if ($isSimple) {
            $firstVariant = $this->product->variants->first();
            if ($firstVariant) {
                // return '';
            }
        }

        $isInstantCheckout = false;
        $hasSubscription = $this->product->has_subscription;
        $buttonText = __('View Options', 'fluent-cart');
        $ariaLabel = sprintf(__('View options for %s', 'fluent-cart'), $this->product->post_title);

        if ($isSimple) {
            if ($hasSubscription) {
                $buttonText = __('Buy Now', 'fluent-cart');
                $ariaLabel = sprintf(__('Buy %s now', 'fluent-cart'), $this->product->post_title);
                $buttonHref = $firstVariant->getPurchaseUrl();
                $isInstantCheckout = true;
            } else {
                $buttonText = __('Add to Cart', 'fluent-cart');
                $ariaLabel = sprintf(__('Add %s to cart', 'fluent-cart'), $this->product->post_title);
            }
        }

        $buttonAttributes = [
                'class'                                            => 'fc-product-view-button fc-single-product-card-view-button',
                'data-product-id'                                  => $this->product->ID,
                'data-fluent-cart-single-product-card-view-button' => '',
                'aria-label'                                       => $ariaLabel
        ];

        if ($firstVariant) {
            $buttonAttributes = [
                    'data-cart-id'                        => $firstVariant->id,
                    'class'                               => 'fluent-cart-add-to-cart-button',
                    'data-variation-type'                 => $this->product->detail->variation_type,
                    'data-fluent-cart-add-to-cart-button' => '',
                    'aria-label'                          => $ariaLabel
            ];
        }
        ?>
        <?php if ($isInstantCheckout): ?>
        <a href="<?php echo esc_url($buttonHref); ?>"
           class="fc-product-view-button"
           aria-label="<?php echo esc_attr($ariaLabel); ?>">
            <span aria-hidden="true">
                <?php echo esc_html($buttonText); ?>
            </span>
        </a>
    <?php else: ?>
        <button
                type="button"
                data-button-url="<?php echo esc_url($buttonHref); ?>"
                <?php $this->renderAttributes($buttonAttributes); ?>>
            <span class="fc-button-text">
                <?php echo esc_html($buttonText); ?>
            </span>
            <span style="display: none;"
                  class="fluent-cart-loader"
                  role="status"
                  aria-live="polite"
                  aria-label="<?php echo esc_attr__('Loading', 'fluent-cart'); ?>">
                <svg aria-hidden="true"
                     class="w-5 h-5 mr-2 text-gray-200 animate-spin fill-blue-600"
                     viewBox="0 0 100 101"
                     fill="none"
                     xmlns="http://www.w3.org/2000/svg"
                     focusable="false">
                      <path
                              d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z"
                              fill="currentColor"></path>
                      <path
                              d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z"
                              fill="currentFill"></path>
                </svg>
            </span>
        </button>
    <?php endif; ?>
        <?php
    }

    protected function renderAttributes($atts = [])
    {
        foreach ($atts as $attr => $value) {
            if ($value !== '') {
                echo esc_attr($attr) . '="' . esc_attr((string)$value) . '" ';
            } else {
                echo esc_attr($attr) . ' ';
            }
        }
    }
}
