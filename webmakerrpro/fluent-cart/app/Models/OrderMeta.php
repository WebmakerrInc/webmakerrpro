<?php

namespace FluentCart\App\Models;

use FluentCart\App\Models\Concerns\CanSearch;

/**
 *  Order Meta Model - DB Model for Order Meta table
 *
 *  Database Model
 *
 * @package FluentCart\App\Models
 *
 * @version 1.0.0
 */
class OrderMeta extends Model
{
    use CanSearch;

    protected $table = 'fct_order_meta';

    protected $fillable = [
        'order_id',
        'meta_key',
        'meta_value',
    ];

    public function setMetaValueAttribute($value)
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->attributes['meta_value'] = $value;
    }


    public function getMetaValueAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded ?: $value;
        }

        return $value;
    }


	/**
	 * One2One: OrderTransaction belongs to one Order
	 *
	 * @return \FluentCart\Framework\Database\Orm\Relations\BelongsTo
	 */
	public function order() {
		return $this->belongsTo( Order::class, 'order_id', 'id' );
	}
}
