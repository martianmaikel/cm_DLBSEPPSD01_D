<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider',
        'model',
        'operation',
        'tokens_input',
        'tokens_output',
        'estimated_cost',
        'latency_ms',
        'status',
        'error_message',
        'event_id',
        'batch_size',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'float',
            'created_at' => 'datetime',
        ];
    }
}
