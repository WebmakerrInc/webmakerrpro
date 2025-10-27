<?php

namespace FluentCart\App\Services\Renderer;


use FluentCart\Api\StoreSettings;
use FluentCart\Framework\Support\Arr;
use FluentCart\App\Modules\Tax\TaxModule;
use FluentCart\App\Services\Localization\LocalizationManager;

class EUVatRenderer
{
    protected $isEUCountry = false;
    protected $taxSettings = [];
    protected $taxApplicableCountry = '';

    public function __construct($isEUCountry = false, $taxApplicableCountry = '')
    {
        $this->taxSettings = (new TaxModule())->getSettings();
        $this->isEUCountry = $isEUCountry;
        $this->taxApplicableCountry = $taxApplicableCountry;
    }

    public function render($cart)
    {
        $euVatEnabled = Arr::get($this->taxSettings, 'eu_vat_settings.require_vat_number', 'no');

        if ($this->isEUCountry && $euVatEnabled === 'yes') {
                $vatNumber = Arr::get($cart->checkout_data, 'tax_data.vat_number', '');
                ?>
                <div class="fct_checkout_form_section">
                    <div class="fct_form_section_header">
                        <h4 class="fct_form_section_header_label"><?php echo esc_html__('EU VAT', 'fluent-cart'); ?></h4>
                    </div>
                    <div class="fct_form_section_body">
                        <div class="fct_tax_field">
                            <div data-fluent-cart-checkout-page-form-input-wrapper class="fct_tax_input_wrapper"
                                 id="fct_billing_tax_id_wrapper">
                                <input
                                    data-fluent-cart-checkout-page-tax-id
                                    type="text"
                                    name="fct_billing_tax_id"
                                    autocomplete="tax-id"
                                    placeholder="<?php echo esc_html__('Enter Tax ID', 'fluent-cart'); ?>"
                                    id="fct_billing_tax_id"
                                    value="<?php echo $vatNumber ?? ''; ?>"
                                />

                                <button data-fluent-cart-checkout-page-tax-apply-btn>
                                    <?php echo esc_html__('Apply', 'fluent-cart'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                    <span data-fluent-cart-checkout-page-tax-loading class="fct_tax_loading"></span>
                    <span data-fluent-cart-checkout-page-form-error class="fct_form_error"></span>
                   <?php $this->renderValidNote($cart->checkout_data); ?>
              </div>
            <?php
        } else {
            return '';
        }

    }

    public function renderValidNote($checkoutData)
    {
        $isValid = Arr::get($checkoutData, 'tax_data.valid', false);
        ?>

        <div class="fct_vat_valid_note <?php echo !$isValid ? 'is-hidden' : ''; ?>" data-fluent-cart-tax-valid-note-wrapper>
                <span data-fluent-cart-tax-valid-note >
                    <?php echo Arr::get($checkoutData, 'tax_data.name', '');?>
                    <?php if (Arr::get($checkoutData, 'tax_data.tax_total') != 0): ?>
                        <span style="color: #ffa500;">
                            <?php echo esc_html__('(Reverse Charge not applied)', 'fluent-cart'); ?>
                        </span>
                    <?php endif; ?>
                </span>

            <button data-fluent-cart-tax-remove-btn>
                <?php echo esc_html__('Remove', 'fluent-cart'); ?>
            </button>
        </div>

        <?php
    }
}
