<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'url',
        'connector_config',
        'connector_class',
        'source_family_id',
        'polling_interval',
        'reliability_score',
        'active',
        'last_polled_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'reliability_score' => 'decimal:2',
            'last_polled_at' => 'datetime',
            'connector_config' => 'array',
        ];
    }

    /**
     * Get a config value from the connector_config JSON.
     */
    public function connectorConfig(string $key, mixed $default = null): mixed
    {
        return data_get($this->connector_config, $key, $default);
    }

    public function sourceFamily(): BelongsTo
    {
        return $this->belongsTo(SourceFamily::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeDueForPolling(Builder $query): Builder
    {
        return $query->active()->where(function (Builder $q) {
            $q->whereNull('last_polled_at')
              ->orWhereRaw("last_polled_at + (polling_interval || ' minutes')::interval <= NOW()");
        });
    }
}
