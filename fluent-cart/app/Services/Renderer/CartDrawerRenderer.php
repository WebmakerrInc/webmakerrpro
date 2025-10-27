<?php

namespace FluentCart\App\Services\Renderer;
use FluentCart\Api\StoreSettings;
use FluentCart\Framework\Support\Arr;
use FluentCart\App\Vite;
use FluentCart\App\Helpers\Helper;
use FluentCart\App\Helpers\CartCheckoutHelper;

class CartDrawerRenderer
{
    protected $cartItems;

    protected $storeSettings;

    protected $itemCount = 0;

    protected $cartRenderer = null;

    protected $defaultOpenCart = false;

    protected $isAdminBarEnabled = false;


    public function __construct($cartItems, $config = [])
    {
        $this->storeSettings = new StoreSettings();
        $this->cartItems = $cartItems;
        $this->itemCount = Arr::get($config, 'item_count') ?? 0;
        $this->defaultOpenCart = Arr::get($config, 'open_cart') ?? false;

        $this->isAdminBarEnabled = Arr::get($config, 'is_admin_bar_enabled') || is_admin_bar_showing() ?? false;


        $this->cartRenderer = new CartRenderer($cartItems);

    }

    public function render(){
        ?>

            <div class="fc-cart-drawer-container" data-fluent-cart-cart-drawer-container>
                <div class="fc-cart-drawer-overlay <?php echo esc_attr($this->defaultOpenCart ? 'active' : '') ?>" data-fluent-cart-cart-drawer-overlay></div>

                <div 
                    class="fc-cart-drawer <?php echo esc_attr($this->defaultOpenCart ? 'open' : '') ?> <?php echo esc_attr($this->isAdminBarEnabled ? 'admin_bar_enabled' : '') ?>"
                    data-fluent-cart-cart-drawer
                >
                    <?php $this->renderCartLoader(); ?>

                    <?php $this->renderHeader(); ?>

                    <?php $this->cartRenderer->renderItems(); ?>

                    <?php $this->renderFooter(); ?>

                    

                </div>

                <div class="fc-cart-drawer-open-btn <?php echo esc_attr($this->itemCount > 0 ? '' : 'is-hidden') ?>"
                    data-fluent-cart-cart-expand-button>

                    <img src="<?php echo esc_url(Vite::getAssetUrl('images/cart.svg')) ?>" alt="<?php esc_attr_e('Cart', 'fluent-cart') ?>"/>

                    <?php $this->renderItemCount(); ?>
                </div>

            </div>

        <?php
    }

    public function renderCartLoader()
    {
        ?>
        <div class="fc-cart-drawer-loader" data-fluent-cart-cart-drawer-loader>
            <div class="fc-cart-drawer-loader-spin-wrap"></div>
        </div>
        <?php
    }
    
    public function renderHeader(){
        ?>

             <div class="fc-cart-drawer-header">
                <h5 class="title">
                    <?php esc_html_e('Shopping Cart', 'fluent-cart');?>
                    <span>
                        &#40;
                            <span data-fluent-cart-cart-total-item class="fluent-cart-cart-item-counter">
                                <?php echo esc_html($this->itemCount); ?>
                            </span>
                            <?php esc_html_e('Items', 'fluent-cart') ?> 
                        &#41;
                    </span>
                </h5>

                 <button
                         data-fluent-cart-cart-collapse-button=""
                         class="fc-cart-drawer-close-button"
                         title="<?php esc_attr_e('Close Cart', 'fluent-cart'); ?>"
                 >
                     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                         <path d="M15.0004 1L1.00043 15M1.00043 1L15.0004 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                     </svg>
                 </button>
            </div>

        <?php
    }
    
    public function renderFooter(){
        ?>

            <div class="fc-cart-drawer-footer">
                <?php $this->cartRenderer->renderTotal(); ?>

                <div class="fc-cart-drawer-footer-actions">
                    <?php
                        $this->cartRenderer->renderCheckoutButton();
                        $this->cartRenderer->renderViewCartButton();
                    ?>
                </div>
            </div>

        <?php
    }

    public function renderItemCount()
    {
        ?>
        <div class="fc-cart-badge-count">
            <?php
                if ($this->itemCount > 0) {
                    echo esc_html($this->itemCount);
                }
            ?>
        </div>
        <?php
    }








}
