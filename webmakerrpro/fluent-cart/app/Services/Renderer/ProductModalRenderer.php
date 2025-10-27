<?php
namespace FluentCart\App\Services\Renderer;

use FluentCart\App\Models\Product;

class ProductModalRenderer
{
    protected $product;
    protected $config = [];

    public function __construct(Product $product, $config = [])
    {
        $this->product = $product;
        $this->config = $config;
    }

    public function render()
    {
        ?>
        <div class="fc-product-modal" data-fluent-cart-shop-app-single-product-modal>
            <div data-fluent-cart-shop-app-single-product-modal-overlay class="fc-product-modal-overlay" >
            </div>
            <div class="fc-product-modal-body">
                <div class="fc-product-modal-close" data-fluent-cart-shop-app-single-product-modal-close>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M12.8337 1.16663L1.16699 12.8333M1.16699 1.16663L12.8337 12.8333" stroke="#2F3448" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <?php (new ProductRenderer($this->product))->render(); ?>
            </div>
        </div>
        <?php

    }

}
