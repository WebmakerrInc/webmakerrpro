<?php

namespace FluentCart\App\Services\Renderer;

use FluentCart\App\Helpers\Helper;
use FluentCart\Framework\Support\Arr;

class ShippingMethodsRender
{
    protected $shippingMethods = [];

    protected $selectedId = null;

    public function __construct($shippingMethods = [], $selectedId = null)
    {
        $this->shippingMethods = $shippingMethods;
        $this->selectedId = $selectedId;
    }

    public function render()
    {
        ?>
        <div class="fct_shipping_methods" id="shipping_methods" data-fluent-cart-checkout-page-shipping-methods-wrapper>
            <div class="fct_checkout_form_section">
                <div class="fct_form_section_header">
                    <h4 class="fct_form_section_header_label"><?php _e('Shipping Options', 'fluent-cart') ?></h4>
                </div>
                <div class="fct_form_section_body">
                    <?php $this->renderBody(); ?>
                </div>
                <span data-fluent-cart-checkout-page-form-error="" for="shipping_method" class="fct_form_error"></span>
            </div>
        </div>
        <?php
    }

    public function renderBody()
    {
        if (is_wp_error($this->shippingMethods)) {
            $this->renderEmpty($this->shippingMethods->get_error_message());
        } else if ($this->shippingMethods) {
            $this->renderMethods();
        } else {
            $this->renderEmptyState();
        }
    }

    public function renderEmptyState()
    {
        ?>
        <div class="fc-empty-state">
            <?php echo __('No shipping methods available for this address.', 'fluent-cart') ?>
        </div>
        <?php
    }

    public function renderMethods()
    {
        if(is_wp_error($this->shippingMethods)) {
            return;
        }
        ?>
        <div class="fct_shipping_methods_list" data-fluent-cart-checkout-page-shipping-method-wrapper>
            <input type="hidden" name="fc_selected_shipping_method" value="<?php echo esc_attr($this->selectedId); ?>">
            <?php foreach ($this->shippingMethods as $shippingMethod) : ?>
                <div class="fct_shipping_methods_item">
                    <input
                        type="radio"
                        <?php echo checked($this->selectedId, $shippingMethod->id); ?>
                        name="fc_shipping_method"
                        id="shipping_method_<?php echo esc_attr($shippingMethod->id); ?>"
                        value="<?php echo esc_attr($shippingMethod->id); ?>"
                    />
                    <label for="shipping_method_<?php echo esc_attr($shippingMethod->id); ?>">
                        <?php
                        $description = Arr::get($shippingMethod->meta, 'description', '');
                        ?>
                        <?php echo esc_html($shippingMethod->title); ?>
                        <span class="shipping-method-amount"><?php echo esc_html(Helper::toDecimal($shippingMethod->charge_amount)); ?></span>
                        <span class="fc-checkmark"></span>
                        <?php if (!empty($description)) : ?>
                            <small class="fct_shipping_method_description"><?php echo esc_html($description); ?></small>
                        <?php endif; ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
//        do_action('fluent_cart/views/checkout_page_shipping_method_list', [
//            'shipping_methods' => $this->shippingMethods
//        ]);
    }

    public function renderEmpty($message)
    {
        ?>
        <div class="fc-empty-state">
            <?php echo wp_kses_post($message); ?>
        </div>
        <?php
    }
}
