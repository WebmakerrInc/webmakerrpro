<?php

namespace FluentCart\App\Hooks\Handlers\BlockEditors\ShopApp\InnerBlocks;

use FluentCart\Api\Contracts\CanEnqueue;
use FluentCart\Api\Resource\ShopResource;
use FluentCart\Api\StoreSettings;
use FluentCart\App\App;
use FluentCart\App\Helpers\Helper;
use FluentCart\App\Models\Product;
use FluentCart\App\Modules\Data\ProductDataSetup;
use FluentCart\App\Modules\Templating\TemplateLoader;
use FluentCart\App\Services\Translations\TransStrings;
use FluentCart\Framework\Pagination\CursorPaginator;
use FluentCart\Framework\Support\Arr;
use FluentCart\App\Vite;

class InnerBlocks
{
    use CanEnqueue;

    public static $parentBlock = 'fluent-cart/products';

    public array $blocks = [];

    public static function textBlockSupport(): array
    {
        return [
                'html'       => false,
                'align'      => ['left', 'center', 'right'],
                'typography' => [
                        'fontSize'   => true,
                        'lineHeight' => true
                ],
                'spacing'    => [
                        'margin' => true
                ],
                'color'      => [
                        'text' => true,
                ]
        ];
    }

    public static function buttonBlockSupport(): array
    {
        return [
                'html'       => false,
                'align'      => ['left', 'center', 'right'],
                'typography' => [
                        'fontSize'      => true,
                        'lineHeight'    => true,
                        'fontWeight'    => true,
                        'textTransform' => true,
                ],
                'spacing'    => [
                        'margin'  => true,
                        'padding' => true,
                ],
                'color'      => [
                        'text'       => true,
                        'background' => true,
                ],
                'border'     => [
                        'radius' => true,
                        'color'  => true,
                        'width'  => true,
                ],
                'shadow'     => true,
        ];
    }


    public static function register()
    {
        $self = new self();
        $blocks = $self->getInnerBlocks();

        foreach ($blocks as $block) {

            register_block_type($block['slug'], [
                    'apiVersion'      => 3,
                    'api_version'     => 3,
                    'version'         => 3,
                    'title'           => $block['title'],
                    'parent'          => array_merge($block['parent'] ?? [], [static::$parentBlock]),
                    'render_callback' => $block['callback'],
                    'supports'        => Arr::get($block, 'supports', []),
                    'attributes'      => Arr::get($block, 'attributes', []),
                    'uses_context'    => Arr::get($block, 'uses_context', []),
            ]);
        }

        add_action('enqueue_block_editor_assets', function () use ($self) {
            $self->enqueueScripts();
        });

        //$self->enqueueStyles();
    }

    public function getInnerBlocks(): array
    {
        return [
                [
                        'title'     => __('Product Title', 'fluent-cart'),
                        'slug'      => 'fluent-cart/shopapp-product-title',
                        'callback'  => [$this, 'renderTitle'],
                        'component' => 'ProductTitleBlock',
                        'icon'      => 'heading',
                        'supports'  => static::textBlockSupport(),
                        'parent'    => [
                                'fluent-cart/shopapp-product-loop',
                                'fluent-cart/shopapp-product-image',
                                'fluent-cart/product-info'
                        ],
                ],

                [
                        'title'     => __('Product Price', 'fluent-cart'),
                        'slug'      => 'fluent-cart/shopapp-product-price',
                        'callback'  => [$this, 'renderPrice'],
                        'parent'    => [
                                'fluent-cart/shopapp-product-loop',
                                'fluent-cart/shopapp-product-image'
                        ],
                        'component' => 'ProductPriceBlock',
                        'icon'      => 'editor-code',
                        'supports'  => static::textBlockSupport()
                ],

                [
                        'title'     => __('Product Image', 'fluent-cart'),
                        'slug'      => 'fluent-cart/shopapp-product-image',
                        'parent'    => [
                                'fluent-cart/shopapp-product-loop',
                        ],
                        'callback'  => [$this, 'renderImage'],
                        'component' => 'ProductImageBlock',
                        'icon'      => 'format-image'
                ],

                [
                        'title'     => __('Product Button', 'fluent-cart'),
                        'slug'      => 'fluent-cart/shopapp-product-buttons',
                        'callback'  => [$this, 'renderButtons'],
                        'component' => 'ProductButtonBlock',
                        'icon'      => 'admin-links',
                        'parent'    => [
                                'fluent-cart/shopapp-product-loop',
                                'fluent-cart/shopapp-product-image'
                        ],
                ],

                [
                        'title'     => __('Product Excerpt', 'fluent-cart'),
                        'slug'      => 'fluent-cart/shopapp-product-excerpt',
                        'callback'  => [$this, 'renderExcerpt'],
                        'component' => 'ProductExcerptBlock',
                        'icon'      => 'editor-code',
                        'parent'    => [
                                'fluent-cart/shopapp-product-loop',
                                'fluent-cart/shopapp-product-image'
                        ],
                ],


            //Product container -> filter, loop
                [
                        'title'        => __('Product Container', 'fluent-cart'),
                        'slug'         => 'fluent-cart/shopapp-product-container',
                        'callback'     => [$this, 'renderProductContainer'],
                        'component'    => 'ProductContainerBlock',
                        'icon'         => 'screenoptions',
                        'parent'       => ['fluent-cart/products', 'core/column', 'core/group'],
                        'uses_context' => [
                                'fluent-cart/paginator',
                                'fluent-cart/per_page',
                                'fluent-cart/enable_filter',
                                'fluent-cart/product_box_grid_size',
                                'fluent-cart/view_mode',
                                'fluent-cart/filters',
                                'fluent-cart/default_filters',
                                'fluent-cart/order_type',
                                'fluent-cart/live_filter',
                                'fluent-cart/price_format',
                                'fluent-cart/enable_wildcard_filter'
                        ],
                        'supports'     => [
                                'align' => ['wide', 'full'],
                                'html'  => false,
                        ],
                        'attributes'   => array_merge(
                                \WP_Block_Type_Registry::get_instance()->get_registered('core/group')->attributes,
                                [
                                        'customAttr' => ['type' => 'string'],
                                ]
                        )
                ],

                [
                        'title'        => __('Product Filter', 'fluent-cart'),
                        'slug'         => 'fluent-cart/shopapp-product-filter',
                        'callback'     => [$this, 'renderFilter'],
                        'component'    => 'ProductFilterBlock',
                        'icon'         => 'editor-code',
                        'parent'       => ['fluent-cart/shopapp-product-container'],
                        'uses_context' => [
                                'fluent-cart/paginator',
                                'fluent-cart/per_page',
                                'fluent-cart/enable_filter',
                                'fluent-cart/product_box_grid_size',
                                'fluent-cart/view_mode',
                                'fluent-cart/filters',
                                'fluent-cart/default_filters',
                                'fluent-cart/order_type',
                                'fluent-cart/live_filter',
                                'fluent-cart/price_format',
                                'fluent-cart/enable_wildcard_filter'
                        ],
                ],
                [
                        'title'        => __('Product View Switcher', 'fluent-cart'),
                        'slug'         => 'fluent-cart/shopapp-product-view-switcher',
                        'callback'     => [$this, 'renderFilterViewSwitcher'],
                        'component'    => 'ProductFilterViewSwitcherBlock',
                        'icon'         => 'editor-code',
                        'parent'       => [
                                'fluent-cart/products',
                                'core/column'
                        ],
                        'uses_context' => [
                                'fluent-cart/paginator',
                                'fluent-cart/per_page',
                                'fluent-cart/enable_filter',
                                'fluent-cart/product_box_grid_size',
                                'fluent-cart/view_mode',
                                'fluent-cart/filters',
                                'fluent-cart/default_filters',
                                'fluent-cart/order_type',
                                'fluent-cart/live_filter',
                                'fluent-cart/price_format',
                                'fluent-cart/enable_wildcard_filter'
                        ],
                ],
                [
                        'title'        => __('Product No Result', 'fluent-cart'),
                        'slug'         => 'fluent-cart/shopapp-product-no-result',
                        'callback'     => [$this, 'renderNoResultBlock'],
                        'component'    => 'ProductNoResultBlock',
                        'icon'         => 'editor-code',
                        'parent'       => [
                                'fluent-cart/products',
                                'core/column',
                                'core/group',
                                'fluent-cart/shopapp-product-container'
                        ],
                        'uses_context' => [
                                'fluent-cart/paginator',
                                'fluent-cart/per_page',
                                'fluent-cart/enable_filter',
                                'fluent-cart/product_box_grid_size',
                                'fluent-cart/view_mode',
                                'fluent-cart/filters',
                                'fluent-cart/default_filters',
                                'fluent-cart/order_type',
                                'fluent-cart/live_filter',
                                'fluent-cart/price_format',
                                'fluent-cart/enable_wildcard_filter'
                        ],
                ],

                [
                        'title'        => __('Product Filter Search Box', 'fluent-cart'),
                        'slug'         => 'fluent-cart/shopapp-product-filter-search-box',
                        'callback'     => [$this, 'renderFilterSearchBox'],
                        'component'    => 'ProductFilterSearchBlock',
                        'icon'         => 'editor-code',
                        'parent'       => ['fluent-cart/shopapp-product-filter'],
                        'uses_context' => [
                                'fluent-cart/paginator',
                                'fluent-cart/per_page',
                                'fluent-cart/enable_filter',
                                'fluent-cart/product_box_grid_size',
                                'fluent-cart/view_mode',
                                'fluent-cart/filters',
                                'fluent-cart/default_filters',
                                'fluent-cart/order_type',
                                'fluent-cart/live_filter',
                                'fluent-cart/price_format',
                                'fluent-cart/enable_wildcard_filter'
                        ]
                ],

                [
                        'title'     => __('Product Filter Filters', 'fluent-cart'),
                        'slug'      => 'fluent-cart/shopapp-product-filter-filters',
                        'callback'  => [$this, 'renderFilterFilters'],
                        'component' => 'ProductFilterFilters',
                        'icon'      => 'editor-code',
                        'parent'    => ['fluent-cart/shopapp-product-filter']
                ],
                [
                        'title'        => __('Product Filter Button', 'fluent-cart'),
                        'slug'         => 'fluent-cart/shopapp-product-filter-button',
                        'callback'     => [$this, 'renderFilterButton'],
                        'component'    => 'ProductFilterButtonBlock',
                        'icon'         => 'editor-code',
                        'parent'       => ['fluent-cart/shopapp-product-filter'],
                        'uses_context' => [
                                'fluent-cart/live_filter'
                        ]
                ],
                [
                        'title'     => __('Product Filter Apply Button', 'fluent-cart'),
                        'slug'      => 'fluent-cart/shopapp-product-filter-apply-button',
                        'callback'  => [$this, 'renderFilterApplyButton'],
                        'component' => 'ProductFilterApplyButtonBlock',
                        'icon'      => 'editor-code',
                        'parent'    => ['fluent-cart/shopapp-product-filter-button'],
                        'supports'  => static::textBlockSupport()
                ],
                [
                        'title'     => __('Product Filter Reset Button', 'fluent-cart'),
                        'slug'      => 'fluent-cart/shopapp-product-filter-reset-button',
                        'callback'  => [$this, 'renderFilterResetButton'],
                        'component' => 'ProductFilterResetButtonBlock',
                        'icon'      => 'editor-code',
                        'parent'    => ['fluent-cart/shopapp-product-filter-button'],
                        'supports'  => static::buttonBlockSupport()
                ],

                [
                        'title'        => __('Product Loop', 'fluent-cart'),
                        'slug'         => 'fluent-cart/shopapp-product-loop',
                        'callback'     => [$this, 'renderProductLoop'],
                        'component'    => 'ProductLoopBlock',
                        'icon'         => 'screenoptions',
                        'parent'       => ['fluent-cart/products', 'core/column', 'core/group'],
                        'supports'     => [
                                'align' => ['wide', 'full'],
                                'html'  => false,
                        ],
                        'attributes'   => array_merge(
                                \WP_Block_Type_Registry::get_instance()->get_registered('core/group')->attributes,
                                [
                                        'customAttr' => ['type' => 'string'],
                                ]
                        ),
                        'uses_context' => [
                                'fluent-cart/paginator',
                                'fluent-cart/per_page',
                                'fluent-cart/enable_filter',
                                'fluent-cart/product_box_grid_size',
                                'fluent-cart/view_mode',
                                'fluent-cart/filters',
                                'fluent-cart/default_filters',
                                'fluent-cart/order_type',
                                'fluent-cart/live_filter',
                                'fluent-cart/price_format',
                                'fluent-cart/enable_wildcard_filter'
                        ],
                ],

                [
                        'title'        => __('Product Paginator', 'fluent-cart'),
                        'slug'         => 'fluent-cart/product-paginator',
                        'callback'     => [$this, 'renderProductPaginator'],
                        'component'    => 'InnerBlocks',
                        'icon'         => 'editor-code',
                        'supports'     => static::textBlockSupport(),
                        'uses_context' => [
                                'fluent-cart/paginator',
                                'fluent-cart/per_page'
                        ]
                ],
                [
                        'title'        => __('Product Paginator Info', 'fluent-cart'),
                        'slug'         => 'fluent-cart/product-paginator-info',
                        'callback'     => [$this, 'renderProductPaginatorInfo'],
                        'parent'       => [
                                'fluent-cart/product-paginator'
                        ],
                        'component'    => 'ProductPaginatorInfoBlock',
                        'icon'         => 'editor-code',
                        'supports'     => static::textBlockSupport(),
                        'uses_context' => [
                                'fluent-cart/paginator',
                                'fluent-cart/per_page',
                                'fluent-cart/enable_filter',
                                'fluent-cart/product_box_grid_size',
                                'fluent-cart/view_mode',
                                'fluent-cart/filters',
                                'fluent-cart/default_filters',
                                'fluent-cart/order_type',
                                'fluent-cart/live_filter',
                                'fluent-cart/price_format',
                                'fluent-cart/enable_wildcard_filter'
                        ],
                ],
                [
                        'title'        => __('Product Paginator Number', 'fluent-cart'),
                        'slug'         => 'fluent-cart/product-paginator-number',
                        'callback'     => [$this, 'renderProductPaginatorNumber'],
                        'parent'       => [
                                'fluent-cart/product-paginator'
                        ],
                        'component'    => 'ProductPaginatorNumberBlock',
                        'icon'         => 'editor-code',
                        'supports'     => static::textBlockSupport(),
                        'uses_context' => [
                                'fluent-cart/paginator',
                                'fluent-cart/per_page',
                                'fluent-cart/enable_filter',
                                'fluent-cart/product_box_grid_size',
                                'fluent-cart/view_mode',
                                'fluent-cart/filters',
                                'fluent-cart/default_filters',
                                'fluent-cart/order_type',
                                'fluent-cart/live_filter',
                                'fluent-cart/price_format',
                                'fluent-cart/enable_wildcard_filter'
                        ],
                ]
        ];
    }


    public function getProductFromBlockContext($block)
    {
        return fluent_cart_get_current_product();
    }

    public function renderFilter($attributes, $content, $block)
    {
        $enableFilter = Arr::get($block->context, 'fluent-cart/enable_filter', false);
        $filters = Arr::get($block->context, 'fluent-cart/filters', []);

        if (!$enableFilter) {
            return '';
        }

        $renderer = new \FluentCart\App\Services\Renderer\ProductFilterRender($filters);
        $innerBlocksContent = '';
        if ($block instanceof \WP_Block && !empty($block->inner_blocks)) {

            $innerBlocksContent .= '<div class="fc-shop-filter-wrapper" data-fluent-cart-shop-app-filter-wrapper>';
            $innerBlocksContent .= '<div class="fc-shop-filter-wrapper-inner">';
            $innerBlocksContent .= '<form class="fc-shop-filter-form" data-fluent-cart-product-filter-form>';
            foreach ($block->inner_blocks as $inner_block) {
                if (isset($inner_block->parsed_block)) {
                    $instance = new \WP_Block($inner_block->parsed_block, $block->context);
                    $instance->context['filter_renderer'] = $renderer;
                    $innerBlocksContent .= $instance->render();
                }
            }
            $innerBlocksContent .= '</form>';
            $innerBlocksContent .= '</div>';
            $innerBlocksContent .= '</div>';

        }
        return $innerBlocksContent;
    }

    public function renderFilterViewSwitcher($attributes, $content, $block)
    {

        $viewMode = Arr::get($block->context, 'fluent-cart/view_mode', 'grid');
        $enableFilter = Arr::get($block->context, 'fluent-cart/enable_filter', false);


        $html = '<div class="fc-shop-view-switcher-wrap">
                <div class="fc-shop-view-switcher">
                    <button type="button" data-fluent-cart-shop-app-grid-view-button="" class="' . ($viewMode === 'grid' ? 'active' : '') . ' grid-view" title="Grid View">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                            <path d="M12.4059 1.59412C13.3334 2.52162 13.3334 4.0144 13.3334 6.99996C13.3334 9.98552 13.3334 11.4783 12.4059 12.4058C11.4784 13.3333 9.98564 13.3333 7.00008 13.3333C4.01452 13.3333 2.52174 13.3333 1.59424 12.4058C0.666748 11.4783 0.666748 9.98552 0.666748 6.99996C0.666748 4.0144 0.666748 2.52162 1.59424 1.59412C2.52174 0.666626 4.01452 0.666626 7.00008 0.666626C9.98564 0.666626 11.4784 0.666626 12.4059 1.59412Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M13.3335 7L0.66683 7" stroke="currentColor" stroke-linecap="round"></path>
                            <path d="M7 0.666626L7 13.3333" stroke="currentColor" stroke-linecap="round"></path>
                        </svg>
                    </button>
                    <button type="button" data-fluent-cart-shop-app-list-view-button="" class="' . ($viewMode === 'list' ? 'active' : '') . ' list-view" title="List View">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M1.33325 7.60008C1.33325 6.8279 1.49441 6.66675 2.26659 6.66675H13.7333C14.5054 6.66675 14.6666 6.8279 14.6666 7.60008V8.40008C14.6666 9.17226 14.5054 9.33341 13.7333 9.33341H2.26659C1.49441 9.33341 1.33325 9.17226 1.33325 8.40008V7.60008Z" stroke="currentColor" stroke-linecap="round"></path>
                            <path d="M1.33325 2.26671C1.33325 1.49453 1.49441 1.33337 2.26659 1.33337H13.7333C14.5054 1.33337 14.6666 1.49453 14.6666 2.26671V3.06671C14.6666 3.83889 14.5054 4.00004 13.7333 4.00004H2.26659C1.49441 4.00004 1.33325 3.83888 1.33325 3.06671V2.26671Z" stroke="currentColor" stroke-linecap="round"></path>
                            <path d="M1.33325 12.9333C1.33325 12.1612 1.49441 12 2.26659 12H13.7333C14.5054 12 14.6666 12.1612 14.6666 12.9333V13.7333C14.6666 14.5055 14.5054 14.6667 13.7333 14.6667H2.26659C1.49441 14.6667 1.33325 14.5055 1.33325 13.7333V12.9333Z" stroke="currentColor" stroke-linecap="round"></path>
                        </svg>
                    </button>
                </div>
                ';

        if ($enableFilter) {
            $html .= '<button type="button" data-fluent-cart-shop-app-filter-toggle-button="" class="fc-shop-filter-toggle-button hide" title="Toggle List">
                    <span>Filter</span>
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2.5 4C1.67157 4 1 3.32843 1 2.5C1 1.67157 1.67157 1 2.5 1C3.32843 1 4 1.67157 4 2.5C4 3.32843 3.32843 4 2.5 4Z" stroke="#2F3448" stroke-width="1.2"></path>
                        <path d="M9.5 11C10.3284 11 11 10.3284 11 9.5C11 8.67157 10.3284 8 9.5 8C8.67157 8 8 8.67157 8 9.5C8 10.3284 8.67157 11 9.5 11Z" stroke="#2F3448" stroke-width="1.2"></path>
                        <path d="M4 2.5L11 2.5" stroke="#2F3448" stroke-width="1.2" stroke-linecap="round"></path>
                        <path d="M8 9.5L1 9.5" stroke="#2F3448" stroke-width="1.2" stroke-linecap="round"></path>
                    </svg>
                </button>';
        }
        $html .= '</div>';
        return $html;
    }

    public function renderFilterSearchBox($attributes, $content, $block): string
    {
        $filterRenderer = Arr::get($block->context, 'filter_renderer');
        $wildcardFilter = Arr::get($block->context, 'fluent-cart/enable_wildcard_filter', false);

        if (!$filterRenderer || !$wildcardFilter) {
            return '';
        }
        ob_start();
        $filterRenderer->renderSearch();
        return ob_get_clean();
    }

    public function renderFilterFilters($attributes, $content, $block)
    {
        $filterRenderer = Arr::get($block->context, 'filter_renderer');
        if (!$filterRenderer) {
            return '';
        }
        ob_start();
        $filterRenderer->renderOptions();
        return ob_get_clean();
    }

    public function renderFilterButton($attributes, $content, $block)
    {
        $liveFilter = Arr::get($block->context, 'fluent-cart/live_filter', true);

        if ($liveFilter) {
            return '';
        }

        $innerBlocksContent = '';
        if ($block instanceof \WP_Block && !empty($block->inner_blocks)) {
            $innerBlocksContent .= '<div class="fc-shop-filter-item"><div class="fc-shop-button-group">';
            foreach ($block->inner_blocks as $inner_block) {
                if (isset($inner_block->parsed_block)) {
                    $instance = new \WP_Block($inner_block->parsed_block, $block->context);
                    $innerBlocksContent .= $instance->render();
                }
            }
            $innerBlocksContent .= '</div></div>';
        }
        return $innerBlocksContent;
    }

    public function renderFilterApplyButton($attributes, $content, $block)
    {
        $wrapper_attribute = get_block_wrapper_attributes([
                'class' => 'fc-shop-apply-filter-button',
        ]);

        return sprintf('<button %s>%s</button>',
                $wrapper_attribute,
                esc_html__('Apply Filter', 'fluent-cart')
        );
    }

    public function renderFilterResetButton($attributes, $content, $block)
    {

        $wrapper_attribute = get_block_wrapper_attributes([
                'class'                                  => 'fc-shop-reset-filter-button',
                'data-fluent-cart-shop-app-reset-button' => '',
        ]);

        return sprintf(
                '<button %s>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M14.2501 9.75V15.75M9.75012 9.75V15.75M20.2498 5.25L3.74976 5.25001M18.7501 5.25V19.5C18.7501 19.6989 18.6711 19.8897 18.5305 20.0303C18.3898 20.171 18.199 20.25 18.0001 20.25H6.00012C5.80121 20.25 5.61044 20.171 5.46979 20.0303C5.32914 19.8897 5.25012 19.6989 5.25012 19.5V5.25M15.7501 5.25V3.75C15.7501 3.35218 15.5921 2.97064 15.3108 2.68934C15.0295 2.40804 14.6479 2.25 14.2501 2.25H9.75012C9.3523 2.25 8.97077 2.40804 8.68946 2.68934C8.40816 2.97064 8.25012 3.35218 8.25012 3.75V5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </button>',
                $wrapper_attribute
        );
    }

    public function renderNoResultBlock($attributes, $content, $block)
    {
        $innerBlocksContent = '';
        if ($block instanceof \WP_Block && !empty($block->inner_blocks)) {
            $innerBlocksContent .= '<div class="fluent-cart-shop-no-result-found hide" data-fluent-cart-shop-no-result-found="">';
            foreach ($block->inner_blocks as $inner_block) {
                if (isset($inner_block->parsed_block)) {
                    $instance = new \WP_Block($inner_block->parsed_block, $block->context);
                    $innerBlocksContent .= $instance->render();
                }
            }
            $innerBlocksContent .= '</div>';
        }
        return $innerBlocksContent;
    }

    public function renderTitle($attributes, $content, $block): string
    {

        $product = $this->getProductFromBlockContext($block);


        if (empty($product)) {
            return 'not found';
        }

        $wrapper_attributes = get_block_wrapper_attributes(
                [
                        'class' => 'fc-product-card-title wc-block-grid__product-title',
                ]
        );

        $render = new \FluentCart\App\Services\Renderer\ProductCardRender($product);
        ob_start();
        $render->renderTitle($wrapper_attributes);
        return ob_get_clean();
    }

    public function renderPrice($attributes, $content, $block): string
    {
        $product = $this->getProductFromBlockContext($block);

        if (empty($product)) {
            return '';
        }

        $wrapper_attributes = get_block_wrapper_attributes(
                [
                        'class' => 'fc-product-card-prices',
                ]
        );

        $render = new \FluentCart\App\Services\Renderer\ProductCardRender($product);
        ob_start();
        $render->renderPrices($wrapper_attributes);
        return ob_get_clean();
    }

    public function renderImage($attributes, $content, $block): string
    {
        $product = $this->getProductFromBlockContext($block);
        if (empty($product)) {
            return '';
        }
        $render = new \FluentCart\App\Services\Renderer\ProductCardRender($product);
        $renderedImage = '';


        $innerBlocksContent = '';
        if ($block instanceof \WP_Block && !empty($block->inner_blocks)) {
            $innerBlocksContent .= '<div class="fc-product-image-inner-blocks">';
            foreach ($block->inner_blocks as $inner_block) {
                if (isset($inner_block->parsed_block)) {
                    $instance = new \WP_Block($inner_block->parsed_block, $block->context);
                    $innerBlocksContent .= $instance->render();
                }
            }
            $innerBlocksContent .= '</div>';
        }
        ob_start();
        $render->renderProductImage();
        $renderedImage = ob_get_clean();

        if (!empty($innerBlocksContent)) {
            return sprintf(
                    "<div class='fc-product-image-wrap' style='position: relative;'>
                       <div>%s</div>
                       
                       <div style='position: absolute; top: 0; left: 0; width: 100%%; height: 100%%;'>
                            %s
                       </div>
                </div>",
                    $renderedImage,
                    $innerBlocksContent
            );
        }

        return $renderedImage;
    }

    public function renderButtons($attributes, $content, $block): string
    {
        $product = $this->getProductFromBlockContext($block);
        if (empty($product)) {
            return '';
        }

        $render = new \FluentCart\App\Services\Renderer\ProductCardRender($product);
        ob_start();
        $render->showBuyButton();
        return ob_get_clean();
    }

    public function renderExcerpt($attributes, $content, $block): string
    {
        $product = $this->getProductFromBlockContext($block);
        if (empty($product) || empty($product->post_excerpt)) {
            return '';
        }
        $render = new \FluentCart\App\Services\Renderer\ProductCardRender($product);
        ob_start();
        $render->renderExcerpt();
        return ob_get_clean();
    }

    public function renderProductLoop($attributes, $content, $block): string
    {


        $lastChanged = $attributes['last_changed'];
        $clientId = Arr::get($attributes, 'wp_client_id', '');
        if ($clientId) {
            $blockId = 'fct_product_loop_client_' . $attributes['wp_client_id'];
            $existingTransient = get_transient($blockId);
            $needsReset = false;
            if (empty($existingTransient) || !is_array($existingTransient)) {
                $needsReset = true;
            } else {
                // Check if last_changed has changed
                if (!empty($lastChanged) && $lastChanged !== $existingTransient['last_changed']) {
                    $needsReset = true;
                } else {
                    // Check age of the transient
                    $setAt = strtotime($existingTransient['set_at'] ?? '');
                    if ($setAt && (time() - $setAt) > (4 * DAY_IN_SECONDS)) {
                        $needsReset = true;
                    }
                }
            }

            if ($needsReset) {
                $raw_block_markup = $block->parsed_block['blockName']
                        ? serialize_block($block->parsed_block)
                        : '';
                $newTransient = [
                        'set_at'       => gmdate('Y-m-d H:i:s'),
                        'last_changed' => $lastChanged,
                        'markup'       => $raw_block_markup,
                ];
                // Store for 7 days
                set_transient($blockId, $newTransient, 7 * DAY_IN_SECONDS);
            }
        }

        $products = $this->getInitialProducts($block);
        $products = $products['products'];

        $parsed = '';
        if ($products instanceof CursorPaginator) {
            $parsed = wp_parse_args(wp_parse_url($products->nextPageUrl(), PHP_URL_QUERY));
        }

        ProductDataSetup::setProductsCache($products);

        $innerBlocksContent = '';
        foreach ($products as $key => $product) {
            setup_postdata($product->ID);
            if ($block instanceof \WP_Block && !empty($block->inner_blocks)) {
                if ($key === 0) {
                    //add attribute provider with value wp-block
                    $innerBlocksContent .= '<div class="fc-product-card" data-template-provider="wp-block" data-fc-product-card data-fluent-cart-cursor="' . esc_attr(Arr::get($parsed, 'cursor', '')) . '" data-fluent-client-id="' . esc_attr($clientId) . '">';
                } else {
                    $innerBlocksContent .= '<div class="fc-product-card" data-fc-product-card>';
                }

                foreach ($block->inner_blocks as $inner_block) {
                    if (isset($inner_block->parsed_block)) {
                        $instance = new \WP_Block($inner_block->parsed_block, $block->context);
                        $innerBlocksContent .= $instance->render();
                    }
                }
                $innerBlocksContent .= '</div>';
            }
        }

        wp_reset_postdata();

        if ($products->count() === 0) {
            add_action('wp_footer', function () {
                ?>
                <script>
                    document.addEventListener("DOMContentLoaded", function () {
                        const noResultFound = document.querySelector("[data-fluent-cart-shop-no-result-found]");
                        if (noResultFound) {
                            noResultFound.classList.remove("hide");
                        }
                    });
                </script>
                <?php
            });
        }
        return $innerBlocksContent;
    }


    public function renderProductContainer($attributes, $content, $block): string
    {
        $isFilterEnabled = Arr::get($block->context, 'fluent-cart/enable_filter', false);
        $ff = Arr::get($block->context, 'fluent-cart/filters', []);
        $paginator = Arr::get($block->context, 'fluent-cart/paginator', 'scroll');
        $productBoxSize = Arr::get($block->context, 'fluent-cart/product_box_grid_size', 4);
        $perPage = Arr::get($block->context, 'fluent-cart/per_page', 10);
        $orderType = Arr::get($block->context, 'fluent-cart/order_type', 'DESC');
        $liveFilter = Arr::get($block->context, 'fluent-cart/live_filter', true);
        $priceFormat = Arr::get($block->context, 'fluent-cart/price_format', 'starts_from');
        $viewMode = Arr::get($block->context, 'fluent-cart/view_mode', 'grid');
        $defaultFilters = Arr::get($block->context, 'fluent-cart/default_filters', []);
        $innerBlocksContent = '';

        if (is_main_query()) {
            $taxonomy = TemplateLoader::$currentTaxonomy;
            if ($taxonomy) {
                $defaultFilters = [
                        $taxonomy->taxonomy => [$taxonomy->term_id],
                        'enabled'           => true
                ];
            }
        }

        Vite::enqueueStyle(
                'fluentcart-add-to-cart-btn-css',
                'public/buttons/add-to-cart/style/style.scss'
        );

        Vite::enqueueStyle(
                'fluentcart-direct-checkout-btn-css',
                'public/buttons/direct-checkout/style/style.scss'
        );

        Vite::enqueueStyle(
                'fluentcart-single-product-page-css',
                'public/single-product/single-product.scss',
        );

        Vite::enqueueStyle(
                'fluentcart-zoom-css',
                'public/lib/xzoom/xzoom.css',
        );

        Vite::enqueueStyle(
                'fluentcart-similar-product-page-css',
                'public/single-product/similar-product.scss',
        );

        Vite::enqueueStyle(
                'fluentcart-product-card-page-css',
                'public/product-card/style/product-card.scss',
        );

        Vite::enqueueScript(
                'fluentcart-zoom-js',
                'public/lib/xzoom/xzoom.js',
                []
        );

        Vite::enqueueScript(
                'fluentcart-single-product-page-js',
                'public/single-product/SingleProduct.js',
                []
        )->with([
                'fluentcart_single_product_vars' => [
                        'trans'                      => TransStrings::singleProductPageString(),
                        'cart_button_text'           => __('Add To Cart', 'fluent-cart'),
                        // App::storeSettings()->get('cart_button_text', __('Add to Cart', 'fluent-cart')),
                        'out_of_stock_button_text'   => App::storeSettings()->get('out_of_stock_button_text', __('Out of Stock', 'fluent-cart')),
                        'in_stock_status'            => Helper::IN_STOCK,
                        'out_of_stock_status'        => Helper::OUT_OF_STOCK,
                        'enable_image_zoom'          => (new StoreSettings())->get('enable_image_zoom_in_single_product'),
                        'enable_image_zoom_in_modal' => (new StoreSettings())->get('enable_image_zoom_in_modal')
                ]
        ]);

        Vite::enqueueScript(
                'fluentcart-similar-product-page-js',
                'public/single-product/SimilarProduct.js',
                []
        );


        if ($block instanceof \WP_Block && !empty($block->inner_blocks)) {
            // start wrapper
            $innerBlocksContent .= sprintf(
                    '<div class="fc-products-wrapper-inner %s%s" data-fluent-cart-product-wrapper-inner  
                        data-per-page="%s"
                        data-order-type="%s"
                        data-live-filter="%s"
                        data-price-format="%s"
                        data-paginator="%s"
                        data-default-filters="%s"
                        >',
                    ($viewMode === 'list' ? 'mode-list' : 'mode-grid'),
                    (!$isFilterEnabled ? ' fc-full-container-width' : ''),
                    esc_attr($perPage),
                    esc_attr($orderType),
                    esc_attr($liveFilter),
                    esc_attr($priceFormat),
                    esc_attr($paginator),
                    esc_attr(wp_json_encode($defaultFilters))
            );

            foreach ($block->inner_blocks as $inner_block) {
                if ('fluent-cart/shopapp-product-filter' === $inner_block->name) {
                    if (!$isFilterEnabled) {
                        continue;
                    }
                }

                $isProductLoop = 'fluent-cart/shopapp-product-loop' === $inner_block->name;

                if ($isProductLoop) {
                    $styleAttr = $productBoxSize ? ' style="--grid-columns: ' . esc_attr($productBoxSize) . ';"' : '';
                    $innerBlocksContent .= '<div class="fc-products-container grid-columns-' . esc_attr($productBoxSize) . '" data-fluent-cart-shop-app-product-list  ' . $styleAttr . '>';
                }

                if (isset($inner_block->parsed_block)) {
                    $instance = new \WP_Block($inner_block->parsed_block, $block->context);
                    $innerBlocksContent .= $instance->render();
                }

                if ($isProductLoop) {
                    $innerBlocksContent .= '</div>';
                }
            }

            // close wrapper
            $innerBlocksContent .= '</div>';

        }
        return $innerBlocksContent;
    }

    protected function getLocalizationKey(): string
    {
        return 'fluent_cart_products_inner_blocks';
    }

    protected function localizeData(): array
    {
        return [
                $this->getLocalizationKey()      => [
                        'blocks' => Arr::except($this->getInnerBlocks(), ['callback']),
                ],
                'fluentcart_single_product_vars' => [
                        'trans'                      => TransStrings::singleProductPageString(),
                        'cart_button_text'           => __('Add To Cart', 'fluent-cart'),
                        // App::storeSettings()->get('cart_button_text', __('Add to Cart', 'fluent-cart')),
                        'out_of_stock_button_text'   => App::storeSettings()->get('out_of_stock_button_text', __('Out of Stock', 'fluent-cart')),
                        'in_stock_status'            => Helper::IN_STOCK,
                        'out_of_stock_status'        => Helper::OUT_OF_STOCK,
                        'enable_image_zoom'          => (new StoreSettings())->get('enable_image_zoom_in_single_product'),
                        'enable_image_zoom_in_modal' => (new StoreSettings())->get('enable_image_zoom_in_modal')
                ]
        ];
    }

    protected function getStyles(): array
    {
        return ['public/single-product/single-product.scss'];
    }

    protected function getScripts(): array
    {
        $scripts = [
                [
                        'source'       => 'admin/BlockEditor/ReactSupport.js',
                        'dependencies' => ['wp-blocks', 'wp-components']
                ],
                [
                        'source'       => 'admin/BlockEditor/ShopApp/InnerBlocks/InnerBlocks.jsx',
                        'dependencies' => ['wp-blocks', 'wp-components', 'wp-block-editor']
                ],
//                [
//                        'source'=> 'public/single-product/SingleProduct.js',
//                        'dependencies' => []
//                ]
        ];

        if (App::isDevMode() || true) {
            $scripts[] = [
                    'source'       => 'admin/BlockEditor/ReactSupport.js',
                    'dependencies' => ['wp-blocks', 'wp-components']
            ];
        }
        return $scripts;
    }

    protected function generateEnqueueSlug(): string
    {
        return 'fluent_cart_shop_app_inner_blocks';
    }

    public function renderProductPaginator($attributes, $content, $block)
    {
        $paginatorType = Arr::get($block->context, 'fluent-cart/paginator', 'scroll');
//        $paginatorType = 'numbers';
        $innerBlocksContent = '';

        if ($paginatorType === 'scroll') {
            return $innerBlocksContent;
        }

        if ($block instanceof \WP_Block && !empty($block->inner_blocks)) {
            $innerBlocksContent .= '<div class="fc-shop-paginator">';
            foreach ($block->inner_blocks as $inner_block) {
                if (isset($inner_block->parsed_block)) {
                    $instance = new \WP_Block($inner_block->parsed_block, $block->context);
                    $innerBlocksContent .= $instance->render();
                }
            }
            $innerBlocksContent .= '</div>';
        }
        return $innerBlocksContent;
    }

    public function renderProductPaginatorInfo($attributes, $content, $block)
    {
        $allProducts = $this->getInitialProducts($block);
        $products = $allProducts['products'];

        $wrapper_attributes = get_block_wrapper_attributes(
                [
                        'class' => 'fc-shop-paginator-results wc-block-grid__fluent-cart-shop-app-paginator-results',
                ]
        );

        $currentPage = Arr::get(App::request()->all(), 'current_page', 1);
        $perPage = $products->perPage();
        $total = $allProducts['total'];

        $start = $currentPage;
        $end = $perPage;

        $html = sprintf(
                '<div class="fc-shop-paginator-result-wrapper">
            <div %s>
                Showing <span class="fc-shop-paginator-from" data-fluent-cart-shop-app-paginator-info-pagination-from>%s</span> of <span class="fc-shop-paginator-to" data-fluent-cart-shop-app-paginator-info-pagination-to>%s</span> of <span class="fc-shop-paginator-total" data-fluent-cart-shop-app-paginator-info-pagination-total>%s</span> Items
            </div>
            
            <div class="fc-shop-per-page-selector">
                <select data-fluent-cart-shop-app-paginator-per-page-selector>
                    <option value="10">%s</option>
                    <option value="20">%s</option>
                    <option value="30">%s</option>
                </select>
            </div>
        </div>',

                // %s (1): The wrapper attributes string (already handled by esc_attr in the context)
                $wrapper_attributes,

                // %s (2, 3, 4): Start, End, and Total counts
                $start,
                $end,
                $total,

                // %s (5, 6, 7): The translated "Per page" strings
                esc_html__('10 Per page', 'fluent-cart'),
                esc_html__('20 Per page', 'fluent-cart'),
                esc_html__('30 Per page', 'fluent-cart')
        );

        return $html;
    }

    public function renderProductPaginatorNumber($attributes, $content, $block)
    {
        $allProducts = $this->getInitialProducts($block);
        $products = $allProducts['products'];

        $currentPage = Arr::get(App::request()->all(), 'current_page', 1);
        $perPage = $products->perPage();
        $total = $allProducts['total'];

        $lastPage = $total / $perPage;

        if ($total % $perPage > 0) {
            $lastPage++;
        }

        $lastPage = intval($lastPage);

        $paginator = [
                'currentPage' => $currentPage,
                'lastPage'    => $lastPage
        ];


        $html = $this->renderPaginatorItems($paginator);
        return $html;
    }

    private function renderPaginatorItems($paginator, $paginationJump = 3)
    {
        if (!$paginator || !isset($paginator['lastPage'], $paginator['currentPage'])) {
            return '';
        }

        $totalPages = (int)$paginator['lastPage'];
        $currentPage = (int)$paginator['currentPage'];
        $jumpSize = (int)$paginationJump;

        $svgLeft = '<svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M12.9168 15.8333L7.7906 10.7071C7.40008 10.3166 7.40008 9.68342 7.7906 9.29289L12.9168 4.16667" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        $svgRight = '<svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M7.91667 4.16666L13.0429 9.29289C13.4334 9.68341 13.4334 10.3166 13.0429 10.7071L7.91667 15.8333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        $html = '';

        // Helper to build button
        $createButton = function ($label, $page, $isActive = false, $isArrow = false) {
            $classes = [];
            if ($isActive) $classes[] = 'active';
            if ($isArrow) $classes[] = 'arrow';

            $classAttr = 'pager-number ';
            if ($classes) {
                $classAttr .= implode(' ', $classes);
            }
            return '<li class="' . esc_attr($classAttr) . '" data-fluent-cart-shop-app-paginator-item data-page="' . $page . '">' . $label . '</li>';
        };

        // ← Previous
        if ($currentPage > 1) {
            $html .= $createButton($svgLeft, $currentPage - 1, false, true);
        }

        // First page
        $html .= $createButton('1', 1, $currentPage == '1');

        // « Jump back
        if ($currentPage > $jumpSize + 1) {
            $jumpBack = max(2, $currentPage - $jumpSize);
            $html .= $createButton('«', $jumpBack, false, true);
        }

        // Pages before current
        for ($i = $currentPage - 2; $i < $currentPage; $i++) {
            if ($i > 1) {
                $html .= $createButton($i, $i);
            }
        }

        // Current page
        if ($currentPage !== 1 && $currentPage !== $totalPages) {
            $html .= $createButton($currentPage, $currentPage, true);
        }

        // Pages after current
        for ($i = $currentPage + 1; $i <= $currentPage + 2; $i++) {
            if ($i < $totalPages) {
                $html .= $createButton($i, $i);
            }
        }

        // » Jump forward
        if ($currentPage < $totalPages - $jumpSize) {
            $jumpForward = min($totalPages - 1, $currentPage + $jumpSize);
            $html .= $createButton('»', $jumpForward, false, true);
        }

        // Last page
        if ($totalPages > 1) {
            $html .= $createButton($totalPages, $totalPages, $currentPage === $totalPages);
        }

        // → Next
        if ($currentPage < $totalPages) {
            $html .= $createButton($svgRight, $currentPage + 1, false, true);
        }

        return '<ul class="fc-shop-paginator-pager" data-fluent-cart-shop-app-paginator-items-wrapper>' . $html . '</ul>';
    }

    public function getInitialProducts($block = null)
    {

        $request = App::request()->all();
        $paginator = Arr::get($block->context, 'fluent-cart/paginator', 'scroll');

        if (App::request()->has('current_page')) {
            $paginator = 'numbers';
        }

        $perPage = Arr::get($block->context, 'fluent-cart/per_page', 10);
        $perPage = Arr::get($request, 'per_page', $perPage);

        $defaultFilters = Arr::get($block->context, 'fluent-cart/default_filters', []);
        $defaultFilters = Arr::get($request, 'default_filters', $defaultFilters);


        $orderType = Arr::get($block->context, 'fluent-cart/order_type', 'DESC');
        $liveFilter = Arr::get($block->context, 'fluent-cart/live_filter', true);

        $priceFormat = Arr::get($block->context, 'fluent-cart/price_format', 'starts_from');
        $priceFormat = Arr::get($request, 'price_format', $priceFormat);

        $wildCardFilter = Arr::get($block->context, 'fluent-cart/enable_wildcard_filter', false);
        $currentPage = Arr::get(App::request()->all(), 'current_page', 1);

        $paginatorMethod = $paginator === 'numbers' ? 'simple' : 'cursor';


        $defaultFilterEnable = Arr::get($defaultFilters, 'enabled', false) ? true : false;

        $allowOutOfStock = $defaultFilterEnable === true &&
                Arr::get($defaultFilters, 'allow_out_of_stock', false) === true;


        if (!$defaultFilterEnable) {
            $defaultFilters = [];
        }

        $urlFilters = Arr::get(App::request()->all(), 'filters', []);

        $status = ["post_status" => ["column" => "post_status", "operator" => "in", "value" => ["publish"]]];

        $urlTerms = Helper::parseTermIdsForFilter($urlFilters);
        $defaultTerms = Helper::parseTermIdsForFilter($defaultFilters);
        $mergedTerms = Helper::mergeTermIdsForFilter($defaultTerms, $urlTerms);

        $mergedTerms = apply_filters('fluent_cart/shop_app_product_query_taxonomy_filters', $mergedTerms, [
                'default_terms'   => $defaultTerms,
                'url_terms'       => $urlTerms,
                'url_filters'     => $urlFilters,
                'default_filters' => $defaultFilters,
                'block'           => $block,
                'is_main_query'   => is_main_query(),
        ]);

        if (is_main_query()) {
            $taxonomy = TemplateLoader::$currentTaxonomy;
            if ($taxonomy) {
                $mergedTerms = [
                        $taxonomy->taxonomy => [$taxonomy->term_id],
                ];
            }
        }

        $params = [
                "select"                   => '*',
                "with"                     => ['detail', 'variants', 'categories', 'licensesMeta'],
                "selected_status"          => true,
                "status"                   => $status,
                "shop_app_default_filters" => $defaultFilters,
                "taxonomy_filters"         => $mergedTerms,
                "paginate"                 => $perPage,
                "per_page"                 => $perPage,
                "page"                     => $currentPage,
                'filters'                  => $urlFilters,
                'paginate_using'           => $paginatorMethod,
                'allow_out_of_stock'       => $allowOutOfStock,
                'order_type'               => $orderType,
                'live_filter'              => $liveFilter
        ];

        $products = ShopResource::get($params);

        return [
                'products' => ($products['products']->setCollection(
                        $products['products']->getCollection()->transform(function ($product) {
                            $product->setAppends(['view_url', 'has_subscription']);
                            return $product;
                        })
                )),
                'total'    => $products['total']
        ];
    }

}
