<?php

namespace FluentSupport\App\Models;

use FluentSupport\App\Services\Helper;

class Tag extends Model
{
    protected $table = 'fs_taggables';

    protected $fillable = ['tag_type', 'title', 'slug', 'description', 'settings', 'created_by'];

    protected $searchable = ['title', 'description'];

    public function setSettingsAttribute($settings)
    {
        $this->attributes['settings'] = \maybe_serialize($settings);
    }

    public function getSettingsAttribute($value)
    {
        return Helper::safeUnserialize($this->attributes['settings']);
    }

    /**
     * Local scope to filter tags by search/query string
     * @param ModelQueryBuilder $query
     * @param string $search
     * @return ModelQueryBuilder
     */

    public function scopeSearchBy($query, $search)
    {
        if ($search) {
            $fields = $this->searchable;
            $query->where(function ($query) use ($fields, $search) {
                $query->where(array_shift($fields), 'LIKE', "%$search%");

                foreach ($fields as $field) {
                    $query->orWhere($field, 'LIKE', "$search%");
                }
            });
        }

        return $query;
    }
}
