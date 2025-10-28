<?php

namespace FluentSupport\App\Models;

class AIActivityLogs extends Model
{
    protected $table = 'fs_ai_activity_logs';

    protected $fillable = ['agent_id', 'ticket_id', 'model_name', 'tokens', 'prompt'];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = current_time('mysql');
            $model->updated_at = current_time('mysql');
        });
    }

    public function person()
    {
        $class = __NAMESPACE__ . '\Person';

        return $this->belongsTo(
            $class, 'agent_id', 'id'
        );
    }

    public function ticket()
    {
        $class = __NAMESPACE__ . '\Ticket';

        return $this->belongsTo(
            $class, 'ticket_id', 'id'
        );
    }
}
