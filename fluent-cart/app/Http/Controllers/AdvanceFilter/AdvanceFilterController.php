<?php

namespace FluentCart\App\Http\Controllers\AdvanceFilter;

use FluentCart\Api\Helper;
use FluentCart\App\Http\Controllers\Controller;
use FluentCart\App\Models\Label;
use FluentCart\App\Services\Filter\LabelFilter;
use FluentCart\App\Services\Filter\OrderFilter;
use FluentCart\App\Services\Filter\ProductFilter;
use FluentCart\App\Services\Filter\VariationFilter;
use FluentCart\Framework\Http\Request\Request;
use FluentCart\Framework\Support\Arr;

class AdvanceFilterController extends Controller
{
    public function getFilterOption(Request $request): \WP_REST_Response
    {
        $args = [
            'remote_data_key' => $request->get('remote_data_key'),
            'search'          => $request->get('search'),
            'include_ids'     => $request->get('include_ids'),
            'limit'           => $request->get('limit'),
        ];


        $dataKey = Arr::get($args, 'remote_data_key');

        $options = [];

        if ($dataKey == 'product_variations') {
            $options = VariationFilter::getTreeFilterOptions($args);
        } else if ($dataKey == 'labels') {
            $options = LabelFilter::getSelectFilterOptions($args);
        } else {
            $options = apply_filters('fluent_cart/advanced_filter_options_' . $dataKey, $options, $args);
        }

        return $this->sendSuccess([
            'options' => $options,
        ]);

    }

    public function getSearchOptions(Request $request)
    {
        return $this->sendSuccess([
            'options' => Helper::getSearchOptions($request->all())
        ]);
    }
}
