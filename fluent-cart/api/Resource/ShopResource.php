<?php

namespace FluentCart\Api\Resource;

use FluentCart\App\Helpers\Helper;
use FluentCart\App\Models\Product;
use FluentCart\App\Models\WpModels\TermRelationship;
use FluentCart\Framework\Database\Orm\Builder;
use FluentCart\Framework\Support\Arr;
use FluentCart\Framework\Support\Str;

class ShopResource extends BaseResourceApi
{

    public static function getQuery(): Builder
    {
        return Product::query();
    }

    /**
     * Get product lists with specified filters.
     *
     * @param array $params Array containing the necessary parameters.
     *
     * $params = [
     *     'filters' => (array) Optional. Additional filters for product retrieval
     *           [
     *             'wildcard' => (string) Optional. Wildcard for filtering by name,
     *             'enable_wildcard_for_post_content' => (int) Optional. Filter for post content,
     *             'categories' => (array) Optional. Filter by Category,
     *             'price_range_from' => (float) Optional. Minimum price for filtering,
     *             'price_range_to' => (float) Optional. Maximum price for filtering
     *           ]
     *     'selected_status' => (bool) Optional. Whether to filter by selected status for Shop only,
     *     'status' => (array) Optional.
     *           [ "post_status" => [
     *               "column" => "post_status",
     *               "operator" => "(string)",
     *               "value" => (string|array) ]
     *           ],
     *    'term_ids_for_filter' => (array) Optional. IDs for filtering by category or tag,
     *     'select' => (string|array) Optional. Columns to select in the query,
     *     'with' => (an array) Optional.  Relationships name to be eager loaded,
     *     "admin_all_statuses" => (array) Optional.
     *            [ "post_status" => [
     *               "column" => "post_status",
     *               "operator" => "(string)",
     *               "value" => (string|array) ]
     *            ],
     *     "admin_search" => (array) Optional.
     *            [ "post_title" => [
     *                "column" => "post_title",
     *                "operator" => "(string)",
     *                "value" => (string|array) ]
     *            ],
     *     "admin_filters" => (array)Optional.
     *             [ "column name" => [
     *                "column" => "column name",
     *                "operator" => "(string)",
     *                "value" => (string|array)]
     *             ],
     *     'order_by' => (string) Optional. Column to order by,
     *     'order_type' => (string) Optional. Order type for sorting (ASC or DESC),
     *     'per_page' => (int) Optional. Number of items for per page,
     *     'page' => (int) Optional. Page number for pagination
     * ];
     */
    public static function get(array $params = []): array
    {
        $shopAppDefaultFilters = Arr::get($params, 'shop_app_default_filters');
        $defaultFilters = Arr::get($params, 'default_filters', []);
        $filters = Arr::get($params, 'filters', []);

        // @TODO: move two below check to appropriate place after checking
        if (is_string($filters)) {
            $filters = json_decode($filters, true) ?: [];
        }
        if (is_string($defaultFilters)) {
            $defaultFilters = json_decode($defaultFilters, true) ?: [];
        }

        $taxonomy_filters = Arr::get($params, 'taxonomy_filters', []);


        $defaultWildcard = Arr::get($defaultFilters, 'wildcard', null);
        $wildcard = Arr::get($filters, 'wildcard', null);

        $status = Arr::get($params, 'status');

        $adminSearch = Arr::get($params, 'admin_search', null);
        $adminFilters = Arr::get($params, 'admin_filters', []);

        $query = static::getQuery()
            ->select(Arr::get($params, 'select', '*'))
            ->with(Arr::get($params, 'with', []));

        $query = apply_filters('fluent_cart/shop_query', $query, $params);

        $query = $query->when(!Arr::get($params, 'selected_status'), function ($query) use ($params) {
                return $query->search(Arr::get($params, 'admin_all_statuses', []));
            })
            ->when($adminSearch, function ($query) use ($adminSearch) {
                return $query->search([
                    'post_title' => [
                        'column'   => 'post_title',
                        'operator' => 'like_all',
                        'value'    => $adminSearch
                    ]
                ])
                    ->orWhere('ID', 'like', '%' . $adminSearch . '%')
                    ->orWhereHas('detail', function ($detailQuery) use ($adminSearch) {
                        $detailQuery->where('fulfillment_type', 'like', '%' . $adminSearch . '%');
                    });
            })
            //Handel default wildcard
            ->when($defaultWildcard, function ($query) use ($defaultWildcard) {
                return $query->search(["post_title" => ["column" => "post_title", "operator" => "like_all", "value" => $defaultWildcard]]);
            })
            ->when($wildcard, function ($query) use ($wildcard, $filters) {
                return $query->search(["post_title" => ["column" => "post_title", "operator" => "like_all", "value" => $wildcard]])
                    ->when(Arr::get($filters, 'enable_wildcard_for_post_content', 0), function ($query) use ($wildcard) {
                        return $query->search(["post_content" => ["column" => "post_content", "operator" => "or_like_all", "value" => $wildcard]]);
                    });
            })
            ->when(Arr::get($shopAppDefaultFilters, 'enabled', 0), function ($query) use ($shopAppDefaultFilters) {
                return $query->when(Arr::get($shopAppDefaultFilters, 'wildcard'), function ($query) use ($shopAppDefaultFilters) {
                    return $query->where(function ($query) use ($shopAppDefaultFilters) {
                        return $query->search(["post_title" => ["column" => "post_title", "operator" => "like_all", "value" => $shopAppDefaultFilters['wildcard']]]);
                    });
                });
            })
            ->when(!empty($filters['price_range_from']) && !empty($filters['price_range_to']), function ($query) use ($filters) {
                return $query->whereHas('detail', function ($query) use ($filters) {
                    return $query->search(["min_price" => ["column" => "min_price", "operator" => "between", "value" => [Helper::toCent($filters['price_range_from']), Helper::toCent($filters['price_range_to'])]]]);
                });
            })
            ->when(!empty($taxonomy_filters), function ($query) use ($taxonomy_filters) {

                //or filter
                foreach ($taxonomy_filters as $taxonomy => $terms) {
                    $query->whereHas('wpTerms', function ($query) use ($terms) {
                        return $query->search(["term_id" => ["column" => "term_id", "operator" => "in", "value" => $terms]]);
                    });
                }
            })
            //We will use it later, if we need to filter by category or type to match any


            ////Below query will match all the products that have all the selected categories or types
//            ->when(count([]), function ($query) use ($and_term_ids) {
//                return $query->whereIn('id', function ($subQuery) use ($and_term_ids) {
//                    $subQuery->select('object_id')
//                        ->from('term_relationships')
//                        ->whereIn('term_taxonomy_id', $and_term_ids)
//                        ->groupBy('object_id')
//                        ->havingRaw('COUNT(DISTINCT term_taxonomy_id) = ?', [count($and_term_ids)]);
//                });
//            })


            //
            ->when($adminFilters, function ($query) use ($adminFilters) {
                return $query->whereHas('detail', function ($query) use ($adminFilters) {
                    return $query->search($adminFilters);
                });
            });

        $totalCount = $query->cloneWithout(['columns', 'orders', 'limit', 'offset', 'joins', 'lock', 'union'])->cloneWithoutBindings(['order'])->count('*');

        // $query = $query
        // ->orderBy(
        //     sanitize_sql_orderby(Arr::get($params, 'order_by', 'ID')),
        //     sanitize_sql_orderby(Arr::get($params, 'order_type', 'ASC'))
        // );

        $orderType = Arr::get($params, 'order_type', 'ASC');
        $orderBy = Arr::get($params, 'order_by', 'ID');

        if (Str::of($orderType)->lower() == 'asc') {
            $query = $query->orderBy($orderBy, 'ASC');
        } else {
            $query = $query->orderBy($orderBy, 'DESC');
        }


        if (Arr::get($params, 'paginate_using') === 'cursor') {
            $products = $query->cursorPaginate(Arr::get($params, 'per_page', 10), ['*'], 'cursor', Arr::get($params, 'cursor'));
        } else {
            $products = $query->simplePaginate(Arr::get($params, 'per_page', 10), ['*'], 'current_page', Arr::get($params, 'page'));
        }

        return [
            'products' => $products,
            'total'    => $totalCount
        ];
    }

    /**
     * Find product by its ID.
     *
     * @param int $productId The ID of the post.
     * @param array $data Additional data for finding product (optional).
     *
     */
    public static function find($productId, $data = []): ?array
    {
        $product = static::getQuery()
            ->with('postmeta')
            ->with('detail')
            ->with('licensesMeta')
            ->with(['variants' => function ($query) {
                $query->with('media')->orderBy('serial_index', 'ASC');
            }])
            ->where('id', $productId)->first();

        if (empty($product)) {
            return null;
        }

        //Below lines are required
        $product->view_url = $product->view_url;
        $product->edit_url = $product->edit_url;
        $product->featured_media = $product->featured_media;

        return $product->toArray();
    }

    /**
     * Retrieve similar product by its ID.
     *
     * @param int $id The ID of the post.
     *
     */
    public static function getSimilarProducts($id, $asArray = true)
    {
        $getProducts = static::getQuery()
            ->with('wpTerms.taxonomy')
            ->where('ID', $id)->first();

        if (empty($getProducts)) {
            return [];
        }

        $termIds = $getProducts->wpTerms->pluck('term_id', 'taxonomy.taxonomy')->toArray();

        foreach ($termIds as &$item) {
            if (!is_array($item)) {
                $item = [$item];
            }
        }

        $params = [
            "select"           => '*',
            "with"             => ['postmeta', 'detail'],
            "selected_status"  => true,
            "status"           => ["post_status" => ["column" => "post_status", "operator" => "in", "value" => ["publish"]], "ID" => ["column" => "ID", "operator" => "!=", "value" => $id]],
            "taxonomy_filters" => $termIds,
            "per_page"         => 5
        ];

        $products = static::get($params);

        $products['products']->setCollection(
            $products['products']->getCollection()->transform(function ($product) {
                return $product->setAppends(['view_url', 'edit_url', 'thumbnail']);
            })
        );

        if($asArray){
            return $products['products']->toArray()['data'];
        }

        return $products;
        
    }

    public static function create($data, $params = [])
    {
    }

    public static function update($productDetail, $postId, $params = [])
    {

    }

    public static function delete($detailId, $params = [])
    {

    }
}
