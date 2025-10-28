<?php

namespace FluentCart\Support\Models;

use FluentCart\Framework\Database\Orm\Model;

class TicketReply extends Model
{
    protected $table = 'fluentcart_ticket_replies';

    protected $guarded = [];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    public $timestamps = false;

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'           => (int) $this->id,
            'ticket_id'    => (int) $this->ticket_id,
            'user_id'      => $this->user_id ? (int) $this->user_id : null,
            'author_name'  => $this->author_name,
            'author_email' => $this->author_email,
            'message'      => $this->message,
            'is_internal'  => (bool) $this->is_internal,
            'created_at'   => $this->created_at,
        ];
    }
}
