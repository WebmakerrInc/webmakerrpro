<?php

namespace FluentCart\Support\Models;

use FluentCart\Framework\Database\Orm\Model;

class Inbox extends Model
{
    protected $table = 'fluentcart_inboxes';

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'is_default' => 'boolean',
    ];

    public $timestamps = false;

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'inbox_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'         => (int) $this->id,
            'title'      => $this->title,
            'email'      => $this->email,
            'is_default' => (bool) $this->is_default,
            'settings'   => $this->settings ?? [],
        ];
    }
}
