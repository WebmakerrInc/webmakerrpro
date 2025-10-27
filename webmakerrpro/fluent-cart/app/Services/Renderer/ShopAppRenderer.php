<?php

namespace FluentCart\App\Services\Renderer;

use FluentCart\Api\Resource\ShopResource;
use FluentCart\Api\Taxonomy;
use FluentCart\App\Modules\Templating\AssetLoader;
use FluentCart\Framework\Pagination\CursorPaginator;
use FluentCart\Framework\Support\Arr;

class ShopAppRenderer
{
    protected $viewMode = 'grid';

    protected $isFilterEnabled = false;

    protected $per_page = 10;

    protected $order_type = 'DESC';

    protected $order_by = 'id';

    protected $liveFilter = true;

    protected $priceFormat = 'starts_from';

    protected $paginator = 'scroll';
    protected $defaultFilters = [];

    protected $productBoxGridSize = 4;

    protected $config = [];

    protected $filters = [];

    protected $products = [];

    protected $customFilters = [];

    public function __construct($products = [], $config = [])
    {
        $defaultFilters = Arr::get($config, 'default_filters', []);
        $customFilters = Arr::get($config, 'custom_filters', []);
        $this->customFilters = $customFilters;
        $enableFilter = false;
        if (!empty($customFilters)) {
            $enableFilter = true;
        }
        $this->config = $config;
        $this->viewMode = $config['view_mode'] ?? 'grid';
        $this->isFilterEnabled = $enableFilter;
        $this->per_page = Arr::get($defaultFilters, 'per_page', 10);
        $this->order_type = Arr::get($defaultFilters, 'sort_type', 'DESC');
        $this->order_by = Arr::get($defaultFilters, 'sort_by', 'id');
        $this->liveFilter = Arr::get($this->customFilters, 'live_filter', true);
        $this->priceFormat = $config['price_format'] ?? 'starts_from';
        $this->paginator = Arr::get($config, 'pagination_type', false);

        if ($this->paginator === 'simple') {
            $this->paginator = 'numbers';
        } else if ($this->paginator === 'cursor') {
            $this->paginator = 'scroll';
        }
        $this->productBoxGridSize = $config['product_box_grid_size'] ?? 4;

        $this->products = $products;

        $this->defaultFilters = array_merge($this->defaultFilters, Arr::get($defaultFilters, 'tax_query', []));
        if (!empty($this->defaultFilters)) {
            $this->defaultFilters['enabled'] = true;
        }

        if (Arr::get($this->customFilters, 'price_range', false)) {
            $this->filters['price_range'] = [
                "filter_type" => "range",
                "is_meta"     => false,
                "label"       => "Price",
                "enabled"     => true,
            ];
        }


        if (isset($this->customFilters['taxonomies'])) {
            foreach ($this->customFilters['taxonomies'] as $key => $taxonomy) {
                if (is_array($taxonomy)) {
                    foreach ($taxonomy as $taxonomyKey => $tax) {
                        $this->filters[$taxonomyKey] = [
                            'enabled'     => true,
                            'filter_type' => 'options',
                            'is_meta'     => true,
                            'label'       => Arr::get($tax, 'label'),
                            'multiple'    => false,
                            'options'     => []
                        ];
                        foreach ($tax['options'] as $option) {
                            $this->filters[$taxonomyKey]['options'][] = [
                                'value'    => $option['term_id'],
                                'label'    => $option['name'],
                                'parent'   => $option['parent'],
                                'children' => []
                            ];
                        }
                    }

                } else {
                    $this->filters[$taxonomy] = [
                        "filter_type" => "options",
                        "is_meta"     => true,
                        "label"       => ucfirst(str_replace('-', ' ', $taxonomy)),
                        "enabled"     => true,
                        "multiple"    => false,
                    ];
                }
            }
        }


        // Example of $this->filters
//        $this->filters = [
//          "product-categories" => [
//            "filter_type" => "options",
//            "is_meta" => true,
//            "label" => "Product Categories",
//            "enabled" => true,
//            "multiple" => false
//          ],
//          "product-types" => [
//            "filter_type" => "options",
//            "is_meta" => true,
//            "label" => "Product Types",
//            "enabled" => true,
//            "multiple" => false
//          ]
//        ];

    }

    public function render()
    {
        AssetLoader::loadProductArchiveAssets();
        $isFullWidth = !$this->isFilterEnabled ? ' fc-full-container-width ' : '';
        $renderer = new \FluentCart\App\Services\Renderer\ProductFilterRender($this->filters);

        $wrapperAttributes = [
            'class'                                  => 'fc-products-wrapper-inner mode-' . $this->viewMode . $isFullWidth,
            'data-fluent-cart-product-wrapper-inner' => '',
            'data-per-page'                          => $this->per_page,
            'data-order-type'                        => $this->order_type,
            'data-live-filter'                       => $this->liveFilter,
            'data-paginator'                         => $this->paginator,
            'data-default-filters'                   => wp_json_encode($this->defaultFilters)
        ];
        ?>
        <div class="fc-products-wrapper" data-fluent-cart-shop-app data-fluent-cart-product-wrapper>
            <?php $this->renderViewSwitcher(); ?>
            <div <?php RenderHelper::renderAtts($wrapperAttributes); ?>>
                <?php if ($this->isFilterEnabled) : ?>
                    <div class="fluent-cart-shop-app-filter-wrapper" data-fluent-cart-shop-app-filter-wrapper>
                        <div class="fluent-cart-shop-app-filter-wrapper-inner">
                            <?php $renderer->render(); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="fc-products-container grid-columns-<?php echo esc_attr($this->productBoxGridSize); ?>"
                     data-fluent-cart-shop-app-product-list>
                    <?php
                    if ($this->products->count() !== 0) {
                        $this->renderProduct();
                    } else {
                        ProductRenderer::renderNoProductFound();
                    }
                    ?>
                </div>
            </div>

            <?php
            if ($this->paginator === 'numbers') {
                $this->renderPaginator();
            }
            ?>

        </div>
        <?php
    }

    public function renderViewSwitcher()
    {

        ?>
        <div class="fc-shop-view-switcher-wrap">
            <div class="fc-shop-view-switcher">
                <button type="button" data-fluent-cart-shop-app-grid-view-button=""
                        class="<?php echo $this->viewMode === 'grid' ? 'active' : ''; ?>"
                        title="Grid View">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path
                            d="M12.4059 1.59412C13.3334 2.52162 13.3334 4.0144 13.3334 6.99996C13.3334 9.98552 13.3334 11.4783 12.4059 12.4058C11.4784 13.3333 9.98564 13.3333 7.00008 13.3333C4.01452 13.3333 2.52174 13.3333 1.59424 12.4058C0.666748 11.4783 0.666748 9.98552 0.666748 6.99996C0.666748 4.0144 0.666748 2.52162 1.59424 1.59412C2.52174 0.666626 4.01452 0.666626 7.00008 0.666626C9.98564 0.666626 11.4784 0.666626 12.4059 1.59412Z"
                            stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M13.3335 7L0.66683 7" stroke="currentColor" stroke-linecap="round"></path>
                        <path d="M7 0.666626L7 13.3333" stroke="currentColor" stroke-linecap="round"></path>
                    </svg>
                </button>
                <button type="button" data-fluent-cart-shop-app-list-view-button=""
                        class="<?php echo $this->viewMode === 'list' ? 'active' : ''; ?>"
                        title="List View">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path
                            d="M1.33325 7.60008C1.33325 6.8279 1.49441 6.66675 2.26659 6.66675H13.7333C14.5054 6.66675 14.6666 6.8279 14.6666 7.60008V8.40008C14.6666 9.17226 14.5054 9.33341 13.7333 9.33341H2.26659C1.49441 9.33341 1.33325 9.17226 1.33325 8.40008V7.60008Z"
                            stroke="currentColor" stroke-linecap="round"></path>
                        <path
                            d="M1.33325 2.26671C1.33325 1.49453 1.49441 1.33337 2.26659 1.33337H13.7333C14.5054 1.33337 14.6666 1.49453 14.6666 2.26671V3.06671C14.6666 3.83889 14.5054 4.00004 13.7333 4.00004H2.26659C1.49441 4.00004 1.33325 3.83888 1.33325 3.06671V2.26671Z"
                            stroke="currentColor" stroke-linecap="round"></path>
                        <path
                            d="M1.33325 12.9333C1.33325 12.1612 1.49441 12 2.26659 12H13.7333C14.5054 12 14.6666 12.1612 14.6666 12.9333V13.7333C14.6666 14.5055 14.5054 14.6667 13.7333 14.6667H2.26659C1.49441 14.6667 1.33325 14.5055 1.33325 13.7333V12.9333Z"
                            stroke="currentColor" stroke-linecap="round"></path>
                    </svg>
                </button>
            </div>

            <?php if ($this->isFilterEnabled) { ?>
                <button type="button"
                        data-fluent-cart-shop-app-filter-toggle-button=""
                        class="fc-shop-filter-toggle-button hide" title="Toggle List">
                    <span><?php echo esc_html__('Filter', 'fluent-cart'); ?></span>
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M2.5 4C1.67157 4 1 3.32843 1 2.5C1 1.67157 1.67157 1 2.5 1C3.32843 1 4 1.67157 4 2.5C4 3.32843 3.32843 4 2.5 4Z"
                            stroke="#2F3448" stroke-width="1.2"></path>
                        <path
                            d="M9.5 11C10.3284 11 11 10.3284 11 9.5C11 8.67157 10.3284 8 9.5 8C8.67157 8 8 8.67157 8 9.5C8 10.3284 8.67157 11 9.5 11Z"
                            stroke="#2F3448" stroke-width="1.2"></path>
                        <path d="M4 2.5L11 2.5" stroke="#2F3448" stroke-width="1.2" stroke-linecap="round"></path>
                        <path d="M8 9.5L1 9.5" stroke="#2F3448" stroke-width="1.2" stroke-linecap="round"></path>
                    </svg>
                </button>
            <?php } ?>
        </div>
        <?php
    }


    public function renderProduct()
    {
        $products = $this->products;

        $cursor = '';
        if ($products instanceof CursorPaginator) {
            $cursor = wp_parse_args(wp_parse_url($products->nextPageUrl(), PHP_URL_QUERY));
        }
        ?>
        <?php foreach ($products as $index => $product) {
        $cursorAttr = '';
        if ($index === 0) {
            $cursorAttr = Arr::get($cursor, 'cursor', '');
        }

        (new \FluentCart\App\Services\Renderer\ProductCardRender($product, ['cursor' => $cursorAttr]))->render();
        ?>
    <?php } ?>
        <?php
    }

    public function renderTitle($product = null)
    {
        if (!$product || !$product === null) {
            return '';
        }

        $render = new \FluentCart\App\Services\Renderer\ProductCardRender($product);
        ob_start();
        $render->renderTitle('class="fc-product-card-title"');
        echo ob_get_clean();
    }

    public function renderImage($product = null)
    {
        if (!$product || !$product === null) {
            return '';
        }

        $render = new \FluentCart\App\Services\Renderer\ProductCardRender($product);
        ob_start();
        $render->renderProductImage();
        echo ob_get_clean();
    }

    public function renderPrice($product = null)
    {
        if (!$product || !$product === null) {
            return '';
        }

        $render = new \FluentCart\App\Services\Renderer\ProductCardRender($product);
        ob_start();
        $render->renderPrices('class="fc-product-card-prices"');
        echo ob_get_clean();
    }

    public function renderButton($product = null)
    {
        if (!$product || !$product === null) {
            return '';
        }

        $render = new \FluentCart\App\Services\Renderer\ProductCardRender($product);
        ob_start();
        $render->showBuyButton();
        echo ob_get_clean();
    }

    private function getInitialProducts()
    {
        $params = $this->config;

        $products = ShopResource::get($params);

        return ($products['products']->setCollection(
            $products['products']->getCollection()->transform(function ($product) {
                $product->setAppends(['view_url', 'has_subscription']);
                return $product;
            })
        ));
    }

    public function renderPaginator()
    {
        $total = $this->products->total();
        $lastPage = max((int)ceil($total / $this->per_page), 1);
        $currentPage = $this->products->currentPage();
        $from = ($currentPage - 1) * $this->per_page + 1;
        $to = min($total, $currentPage * $this->per_page);
        $perPage = $this->products->perPage();
        ?>
        <div class="fc-shop-paginator">
            <div class="fc-shop-paginator-result-wrapper">
                <div
                    class="fc-shop-paginator-results wc-block-grid__fluent-cart-shop-app-paginator-results wp-block-fluent-cart-product-paginator-info">
                    Showing
                    <span class="fc-shop-paginator-from"
                          data-fluent-cart-shop-app-paginator-info-pagination-from="">
                        <?php echo esc_html($from); ?>
                    </span>
                    to
                    <span class="fc-shop-paginator-to"
                          data-fluent-cart-shop-app-paginator-info-pagination-to="">
                        <?php echo esc_html($to); ?>
                    </span>
                    of
                    <span class="fc-shop-paginator-total"
                          data-fluent-cart-shop-app-paginator-info-pagination-total="">
                        <?php echo esc_html($total); ?>
                    </span>
                    Items
                </div>

                <div class="fc-shop-per-page-selector">
                    <select data-fluent-cart-shop-app-paginator-per-page-selector="">
                        <option
                            value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>><?php echo esc_html__('10 Per page', 'fluent-cart'); ?></option>
                        <option
                            value="20" <?php echo $perPage == 20 ? 'selected' : ''; ?>><?php echo esc_html__('20 Per page', 'fluent-cart'); ?></option>
                        <option
                            value="30" <?php echo $perPage == 30 ? 'selected' : ''; ?>><?php echo esc_html__('30 Per page', 'fluent-cart'); ?></option>
                    </select>
                </div>
            </div>

            <?php if ($lastPage > 1) : ?>
            <ul class="fc-shop-paginator-pager"
                data-fluent-cart-shop-app-paginator-items-wrapper="">
                <?php for ($page = 1; $page <= $lastPage; $page++):
                    ?>
                    <li class="pager-number <?php echo $page == $currentPage ? 'active' : ''; ?>"
                        data-fluent-cart-shop-app-paginator-item=""
                        data-page="<?php echo esc_attr($page); ?>">
                        <?php echo esc_html($page); ?>
                    </li>
                <?php endfor; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php
    }

}
