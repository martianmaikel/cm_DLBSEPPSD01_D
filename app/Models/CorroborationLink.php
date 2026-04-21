<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorroborationLink extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_a_id',
        'event_b_id',
        'similarity_score',
        'match_method',
        'cross_family',
    ];

    protected function casts(): array
    {
        return [
            'similarity_score' => 'decimal:4',
            'cross_family' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function eventA(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_a_id');
    }

    public function eventB(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_b_id');
    }
}
