<?php

namespace FluentCart\App\Http\Requests;

use FluentCart\App\Models\ShippingClass;
use FluentCart\App\Services\DateTime\DateTime;
use FluentCart\Framework\Foundation\RequestGuard;
use FluentCart\Framework\Support\Arr;

class ProductCreateRequest extends RequestGuard
{
    /**
     * Prepare and normalize the incoming data before validation.
     *
     * This method ensures that each variant in the request payload has the necessary structure
     * expected for processing, particularly focusing on the `other_info` attribute.
     *
     * If `other_info` is missing from a variant (common during data migration scenarios),
     * this method sets default values to prevent validation or processing errors.
     *
     * It also ensures that:
     * - `fulfillment_type` is consistently applied across all variants, defaulting to 'physical'.
     * - `payment_type` is set (defaulting to 'onetime') and injected into `other_info`.
     * - `other_info` is populated with a consistent structure containing default billing and setup fee options.
     *
     * @return array The normalized request data ready for validation.
     */


    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'post_title'              => 'required|sanitizeText|maxLength:200',
            'post_status'             => ['nullable', 'string'],
            'detail.fulfillment_type' => 'required|sanitizeText|maxLength:100',
        ];
    }


    /**
     * @return array
     */
    public function messages(): array
    {
        return [
            'post_title.required'                               => esc_html__('Title is required.', 'fluent-cart'),
            'post_title.max'                                    => esc_html__('Title may not be greater than 200 characters.', 'fluent-cart'),
            'detail.fulfillment_type.required'                  => esc_html__('Fulfilment Type is required.', 'fluent-cart'),
            'detail.variation_type.required'                    => esc_html__('Variation Type is required.', 'fluent-cart'),
            'variants.required_if'                              => esc_html__('Pricing is required when status is publish or scheduled.', 'fluent-cart'),
            'variants.*.variation_title.required'               => esc_html__('Title is required.', 'fluent-cart'),
            'variants.*.variation_title.max'                    => esc_html__('Title may not be greater than 200 characters.', 'fluent-cart'),
            'variants.*.item_price.required'                    => esc_html__('Price is required.', 'fluent-cart'),
            'variants.*.item_price.numeric'                     => esc_html__('Price must be a number.', 'fluent-cart'),
            'variants.*.item_price.min'                         => esc_html__('Price must be a positive number greater than 0.', 'fluent-cart'),
            'variants.*.stock_status.required_if'               => esc_html__('Stock status is required.', 'fluent-cart'),
            'variants.*.item_cost.required_if'                  => esc_html__('Item cost is required.', 'fluent-cart'),
            'variants.*.other_info.description.max'             => esc_html__('Description may not be greater than 255 characters.', 'fluent-cart'),
            'variants.*.other_info.payment_type.required'       => esc_html__('Payment Type is required.', 'fluent-cart'),
            'variants.*.other_info.times.required_if'           => esc_html__('Times is required.', 'fluent-cart'),
            'variants.*.other_info.repeat_interval.required_if' => esc_html__('Interval is required.', 'fluent-cart'),
            'variants.*.other_info.signup_fee.required_if'      => esc_html__('Setup Fee Amount is required.', 'fluent-cart'),
            'variants.*.other_info.signup_fee_name.required_if' => esc_html__('Setup Fee Name is required.', 'fluent-cart'),
        ];
    }


    /**
     * @return array
     */
    public function sanitize()
    {

        return [
            'post_title'              => 'sanitize_text_field',
            'post_status'             => 'sanitize_text_field',
            'detail.fulfillment_type' => 'sanitize_text_field',
        ];

    }
}
