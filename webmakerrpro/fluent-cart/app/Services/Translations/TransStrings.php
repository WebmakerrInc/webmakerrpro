<?php

namespace FluentCart\App\Services\Translations;
class TransStrings
{
    public static function getStrings(): array
    {
        $translations = require 'admin-translation.php';
        return apply_filters("fluent_cart/admin_translations", $translations, []);
    }

    public static function blockStrings(): array
    {
        $translations = require 'block-editor-translation.php';
        return apply_filters("fluent_cart/blocks_translations", $translations, []);
    }


    public static function getShopAppBlockEditorString(): array
    {

        return [
            'Also search in Content'            => _x('Also search in Content', 'Shop App Block Editor', 'fluent-cart'),
            'Apply Filter'                      => _x('Apply Filter', 'Shop App Block Editor', 'fluent-cart'),
            'Add to Cart'                       => _x('Add to Cart', 'Shop App Block Editor', 'fluent-cart'),
            'Default'                           => _x('Default', 'Shop App Block Editor', 'fluent-cart'),
            'Display Name For Filter'           => _x('Display Name For Filter', 'Shop App Block Editor', 'fluent-cart'),
            'Enable Default Filtering'          => _x('Enable Default Filtering', 'Shop App Block Editor', 'fluent-cart'),
            'Enable Filtering'                  => _x('Enable Filtering', 'Shop App Block Editor', 'fluent-cart'),
            'Enable'                            => _x('Enable', 'Shop App Block Editor', 'fluent-cart'),
            'Enabled?'                          => _x('Enabled?', 'Shop App Block Editor', 'fluent-cart'),
            'Filter Option'                     => _x('Filter Option', 'Shop App Block Editor', 'fluent-cart'),
            'Grid'                              => _x('Grid', 'Shop App Block Editor', 'fluent-cart'),
            'List'                              => _x('List', 'Shop App Block Editor', 'fluent-cart'),
            'Numbers'                           => _x('Numbers', 'Shop App Block Editor', 'fluent-cart'),
            'Option'                            => _x('Option', 'Shop App Block Editor', 'fluent-cart'),
            'Paginator'                         => _x('Paginator', 'Shop App Block Editor', 'fluent-cart'),
            'Per Page'                          => _x('Per Page', 'Shop App Block Editor', 'fluent-cart'),
            'Product Box Grid Size'             => _x('Product Box Grid Size', 'Shop App Block Editor', 'fluent-cart'),
            'Product Categories'                => _x('Product Categories', 'Shop App Block Editor', 'fluent-cart'),
            'Product Grid Size'                 => _x('Product Grid Size', 'Shop App Block Editor', 'fluent-cart'),
            'Product Types'                     => _x('Product Types', 'Shop App Block Editor', 'fluent-cart'),
            'Product'                           => _x('Product', 'Shop App Block Editor', 'fluent-cart'),
            'Range Filter Only works in pages.' => _x('Range Filter Only works in pages.', 'Shop App Block Editor', 'fluent-cart'),
            'Scroll'                            => _x('Scroll', 'Shop App Block Editor', 'fluent-cart'),
            'Search Grid Size'                  => _x('Search Grid Size', 'Shop App Block Editor', 'fluent-cart'),
            'Search'                            => _x('Search', 'Shop App Block Editor', 'fluent-cart'),
            'View mode'                         => _x('View mode', 'Shop App Block Editor', 'fluent-cart'),
            'Wildcard Filter'                   => _x('Wildcard Filter', 'Shop App Block Editor', 'fluent-cart'),

            'Primary'                => _x('Primary', 'Shop App Block Editor', 'fluent-cart'),
            'Product Heading'        => _x('Product Heading', 'Shop App Block Editor', 'fluent-cart'),
            'Text'                   => _x('Text', 'Shop App Block Editor', 'fluent-cart'),
            'Border'                 => _x('Border', 'Shop App Block Editor', 'fluent-cart'),
            'Badge Count Background' => _x('Badge Count Background', 'Shop App Block Editor', 'fluent-cart'),
            'Badge Count'            => _x('Badge Count', 'Shop App Block Editor', 'fluent-cart'),
            'Badge Count Border'     => _x('Badge Count Border', 'Shop App Block Editor', 'fluent-cart'),


            'Background'                => _x('Background', 'Shop App Block Editor', 'fluent-cart'),
            'Input Border'              => _x('Input Border', 'Shop App Block Editor', 'fluent-cart'),
            'Input Focus Border'        => _x('Input Focus Border', 'Shop App Block Editor', 'fluent-cart'),
            'Heading'                   => _x('Heading', 'Shop App Block Editor', 'fluent-cart'),
            'Label'                     => _x('Label', 'Shop App Block Editor', 'fluent-cart'),
            'Item Border'               => _x('Item Border', 'Shop App Block Editor', 'fluent-cart'),
            'Reset Button Bg'           => _x('Reset Button Bg', 'Shop App Block Editor', 'fluent-cart'),
            'Reset Button'              => _x('Reset Button', 'Shop App Block Editor', 'fluent-cart'),
            'Reset Button Border'       => _x('Reset Button Border', 'Shop App Block Editor', 'fluent-cart'),
            'Reset Button Hover Bg'     => _x('Reset Button Hover Bg', 'Shop App Block Editor', 'fluent-cart'),
            'Reset Button Hover'        => _x('Reset Button Hover', 'Shop App Block Editor', 'fluent-cart'),
            'Reset Button Hover Border' => _x('Reset Button Hover Border', 'Shop App Block Editor', 'fluent-cart'),
            'Checkbox'                  => _x('Checkbox', 'Shop App Block Editor', 'fluent-cart'),
            'Checkbox Active'           => _x('Checkbox Active', 'Shop App Block Editor', 'fluent-cart'),
            'Checkmark Bg'              => _x('Checkmark Bg', 'Shop App Block Editor', 'fluent-cart'),
            'Checkmark Border'          => _x('Checkmark Border', 'Shop App Block Editor', 'fluent-cart'),
            'Checkmark Active Bg'       => _x('Checkmark Active Bg', 'Shop App Block Editor', 'fluent-cart'),
            'Checkmark Active Border'   => _x('Checkmark Active Border', 'Shop App Block Editor', 'fluent-cart'),
            'Checkmark After Border'    => _x('Checkmark After Border', 'Shop App Block Editor', 'fluent-cart'),
            'Range Slider Connect Bg'   => _x('Range Slider Connect Bg', 'Shop App Block Editor', 'fluent-cart'),

        ];
    }

    public static function getCustomerProfileString(): array
    {
        $translations = require 'customer-profile-translation.php';
        return apply_filters("fluent_cart/customer_profile_translations", $translations, []);
    }

    public static function singleProductPageString(): array
    {
        return [
            'In Stock'     => _x('In Stock', 'Single Product Page', 'fluent-cart'),
            'Out Of Stock' => _x('Out Of Stock', 'Single Product Page', 'fluent-cart'),
        ];
    }

    public static function checkoutPageString()
    {
        $translations = require 'checkout-translation.php';
        return apply_filters("fluent_cart/checkout_translations", $translations, []);
    }

    public static function paymentsString()
    {
        $translations = require 'payments-translation.php';
        return apply_filters("fluent_cart/payments_translations", $translations, []);
    }
}
