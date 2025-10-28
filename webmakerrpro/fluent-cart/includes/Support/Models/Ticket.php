<?php

namespace FluentCart\Support\Models;

use FluentCart\Framework\Database\Orm\Model;

class Ticket extends Model
{
    protected $table = 'fluentcart_tickets';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    public $timestamps = false;

    public function replies()
    {
        return $this->hasMany(TicketReply::class, 'ticket_id')->orderBy('created_at', 'asc');
    }

    public function inbox()
    {
        return $this->belongsTo(Inbox::class, 'inbox_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'             => (int) $this->id,
            'subject'        => $this->subject,
            'status'         => $this->status,
            'priority'       => $this->priority,
            'inbox_id'       => (int) $this->inbox_id,
            'inbox'          => $this->inbox ? $this->inbox->toApiArray() : null,
            'customer_name'  => $this->customer_name,
            'customer_email' => $this->customer_email,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'closed_at'      => $this->closed_at,
            'last_reply_at'  => $this->last_reply_at,
            'meta'           => $this->meta ?? [],
            'replies'        => $this->replies ? $this->replies->map(fn($reply) => $reply->toApiArray())->all() : [],
        ];
    }
}
