<?php

namespace FluentCart\App\Services\Renderer;

use FluentCart\Framework\Support\Arr;

class ProductListRenderer
{
    protected $products;

    protected $listTitle = null;

    protected $wrapperClass = null;

    protected $cursor = null;

    public function __construct($products, $listTitle = null, $wrapperClass = null)
    {
        $this->products = $products;
        $this->listTitle = $listTitle;
        $this->wrapperClass = $wrapperClass;

        if($products instanceof \FluentCart\Framework\Pagination\CursorPaginator){
            $this->cursor = wp_parse_args(wp_parse_url($products->nextPageUrl(), PHP_URL_QUERY));
            $this->cursor = Arr::get($this->cursor, 'cursor', '');
        }

    }

    public function render()
    {
        ?>
        <div class="fc-product-list-container <?php echo esc_attr($this->wrapperClass); ?>">
            <?php $this->renderTitle(); ?>
            <div class="fc-product-list">
                <?php $this->renderProductList(); ?>
            </div>
        </div>
        <?php
    }

    public function renderProductList()
    {

        foreach ($this->products as $index => $product) {
            $config = [];
            if($index == 0 && $this->cursor){
                $config['cursor'] = $this->cursor;
            }
            (new ProductCardRender($product,$config))->render();
        }
    }

    public function renderTitle() {

        if(!empty($this->listTitle)) : ?>
            <h4 class="fc-product-list-heading">
                <?php echo esc_html($this->listTitle); ?>
            </h4>
        <?php endif;

    }

}
