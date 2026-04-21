<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActorCandidate extends Model
{
    protected $fillable = [
        'normalized_name',
        'actor_type',
        'display_name',
        'mention_events_json',
        'event_count',
        'first_seen_at',
        'last_seen_at',
        'blocked',
    ];

    protected function casts(): array
    {
        return [
            'mention_events_json' => 'array',
            'event_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'blocked' => 'boolean',
        ];
    }

    public function scopeReadyToPromote(Builder $query, int $threshold): Builder
    {
        return $query->where('event_count', '>=', $threshold)->where('blocked', false);
    }
}
