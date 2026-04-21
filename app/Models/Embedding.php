<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Embedding extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'provider',
        'dimensions',
    ];

    protected function casts(): array
    {
        return [
            'dimensions' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }
}
