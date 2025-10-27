<?php

namespace FluentCart\App\Services\Renderer;

use FluentCart\App\Helpers\Helper;
use FluentCart\App\Models\Cart;
use FluentCart\Framework\Support\Arr;

class CartItemRenderer
{
    protected $item = [];
    protected $cart = null;

    protected $product = null;

    protected $variant = null;

    public function __construct($item = [], ?Cart $cart = null)
    {
        $this->item = $item;
        $this->cart = $cart;
    }

    protected function getEventInfo()
    {
        return [
            'item'    => $this->item,
            'cart'    => $this->cart,
            'product' => $this->product,
            'variant' => $this->variant,
        ];
    }

    public function render()
    {
        $wrapperClassAttributes = [
            'fct_line_item',
            'fct_product_id_' . Arr::get($this->item, 'post_id', ''),
            'fct_item_id_' . Arr::get($this->item, 'id', ''),
            'fct_item_type_' . Arr::get($this->item, 'other_info.payment_type', ''),
        ];

        if (Arr::get($this->item, 'featured_media')) {
            $wrapperClassAttributes[] = 'fct_has_image';
        }

        $promoPriceOriginal = Arr::get($this->item, 'other_info.promo_price_original', 0);
        if ($promoPriceOriginal && $promoPriceOriginal > $this->item['unit_price']) {
            $promoPriceOriginal = $promoPriceOriginal * Arr::get($this->item, 'quantity', 1);
        } else {
            $promoPriceOriginal = '';
        }

        ?>
        <div class="<?php $this->renderCssAtts($wrapperClassAttributes); ?>">
            <div class="fct_line_item_info">
                <?php $this->renderImage(); ?>
                <div class="fct_item_content">
                    <?php $this->renderTitle(); ?>
                    <?php do_action('fluent_cart/cart/line_item/line_meta', $this->getEventInfo()); ?>
                </div>
            </div><!-- .fct_line_item_info -->

            <div class="fct_line_item_price">
                <?php do_action('fluent_cart/cart/line_item/before_total', $this->getEventInfo()); ?>
                <?php if($promoPriceOriginal) : ?>
                <div style="text-decoration: line-through;" class="fct_line_item_total fct_promo_price">
                    <?php echo Helper::toDecimal($promoPriceOriginal); ?>
                </div>
                <?php endif; ?>
                <span class="fct_line_item_total">
                    <?php echo Helper::toDecimal(Arr::get($this->item, 'subtotal', 0)); ?>
                </span>
                <?php do_action('fluent_cart/cart/line_item/after_total', $this->getEventInfo()); ?>
            </div><!-- .fct_line_item_price -->
        </div>
        <?php
    }

    public function renderTitle()
    {
        $href = Arr::get($this->item, 'view_url', '');

        $mainTitle = (string) Arr::get($this->item, 'post_title', '');
        $subtitle = (string) Arr::get($this->item, 'title', '');

        $quantity = Arr::get($this->item, 'quantity', 1);

        ?>
        <div class="fct_item_title">
            <?php do_action('fluent_cart/cart/line_item/before_main_title', $this->getEventInfo()); ?>
            <?php if ($quantity > 1): ?>
                <span class="fct_item_quantity">
                    <?php echo esc_attr($quantity); ?> <span>x</span>
                </span>
            <?php endif; ?>
            <?php if ($href): ?>
                <a href="<?php echo esc_url($href); ?>">
                    <?php echo wp_kses_post($mainTitle); ?>
                </a>
            <?php else: ?>
                <?php echo wp_kses_post($mainTitle); ?>
            <?php endif; ?>

            <?php if ($mainTitle != $subtitle && $subtitle): ?>
                <div class="fct_item_variant_title">
                    - <?php echo wp_kses_post($subtitle); ?>
                </div>
            <?php endif; ?>
            <?php $this->maybeRenderPaymentTypeInfo(); ?>

            <?php do_action('fluent_cart/cart/line_item/after_main_title', $this->getEventInfo()); ?>
        </div>
        <?php
    }

    public function renderImage()
    {
        $image = Arr::get($this->item, 'featured_media');
        if (!$image) {
            return;
        }
        $href = Arr::get($this->item, 'view_url', '');
        ?>
        <div class="fct_item_image">
            <?php if ($href): ?>
                <a href="<?php echo esc_url($href); ?>">
                    <img src="<?php echo esc_url($image); ?>"
                         alt="<?php echo esc_attr(sprintf(__('image for %s'), $this->item['title'])); ?>"/>
                </a>
            <?php else: ?>
                <img src="<?php echo esc_url($image); ?>"
                     alt="<?php echo esc_attr(sprintf(__('image for %s'), $this->item['title'])); ?>"/>
            <?php endif; ?>
        </div>
        <?php
    }

    protected function renderCssAtts($atts)
    {
        echo esc_attr(implode(' ', $atts));
    }

    protected function maybeRenderPaymentTypeInfo()
    {
        $otherInfo = Arr::get($this->item, 'other_info', []);
        $paymentType = Arr::get($otherInfo, 'payment_type', '');
        $itemPrice = Arr::get($this->item, 'unit_price', 0);

        if ($paymentType === 'subscription') {
            $subscriptionInfo = Helper::generateSubscriptionInfo($otherInfo, $itemPrice);
            $setupFeeInfo = Helper::generateSetupFeeInfo($otherInfo);
            $trialInfo = Helper::generateTrialInfo($otherInfo);
            ?>
            <div class="fct_item_payment_info">
                <span> <?php echo esc_html($subscriptionInfo); ?> </span>
                <?php if ($trialInfo): ?>
                    <span class="trial-days"> <?php echo esc_html($trialInfo); ?> </span>
                <?php endif; ?>
                <?php if (!empty($setupFeeInfo)): ?>
                    <span class="setup-fee"> <?php echo esc_html($setupFeeInfo); ?> </span>
                <?php endif; ?>
            </div>
            <?php
            return;
        }

        $quantity = Arr::get($this->item, 'quantity', 1);

        if ($quantity < 2) {
            return;
        }
        ?>
        <div class="fct_item_payment_info">
            <?php _e(sprintf('%s each', Helper::toDecimal($itemPrice))); ?>
        </div>
        <?php
    }

}
