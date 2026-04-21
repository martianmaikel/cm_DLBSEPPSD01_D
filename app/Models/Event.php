<?php

namespace App\Models;

use App\Casts\PointCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::saving(function (Event $event) {
            if (! $event->slug && $event->title) {
                $base = Str::slug(Str::limit($event->title, 80, '')) ?: 'event';
                $date = $event->occurred_at?->format('d-m-Y') ?? now()->format('d-m-Y');
                $event->slug = "{$base}-{$date}";
            }
        });
    }

    protected $fillable = [
        'title',
        'title_de',
        'slug',
        'summary',
        'summary_de',
        'raw_content',
        'category',
        'subcategory',
        'severity',
        'severity_factors',
        'confidence',
        'status',
        'country',
        'region',
        'geo_approximate',
        'occurred_at',
        'source_id',
        'source_url',
        'conflict_thread_id',
        'corroboration_count',
        'hash',
        'entities_json',
        'media_urls',
        'classification_attempts',
        'last_reconciled_at',
        'coordinates',
    ];

    protected function casts(): array
    {
        return [
            'severity' => 'integer',
            'severity_factors' => 'array',
            'confidence' => 'integer',
            'geo_approximate' => 'boolean',
            'occurred_at' => 'datetime',
            'entities_json' => 'array',
            'media_urls' => 'array',
            'corroboration_count' => 'integer',
            'classification_attempts' => 'integer',
            'last_reconciled_at' => 'datetime',
            'coordinates' => PointCast::class,
        ];
    }

    // ── Relationships ──

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function conflictThread(): BelongsTo
    {
        return $this->belongsTo(ConflictThread::class);
    }

    public function corroborationLinksAsA(): HasMany
    {
        return $this->hasMany(CorroborationLink::class, 'event_a_id');
    }

    public function corroborationLinksAsB(): HasMany
    {
        return $this->hasMany(CorroborationLink::class, 'event_b_id');
    }

    public function entityExtractions(): HasMany
    {
        return $this->hasMany(EntityExtraction::class);
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(Embedding::class)->where('provider', config('llm.default_embedder'));
    }

    // ── Accessors ──

    public function getIsGeolocatedAttribute(): bool
    {
        return $this->coordinates !== null && ! $this->geo_approximate;
    }

    public function getSourceFamilyAttribute(): ?SourceFamily
    {
        return $this->source?->sourceFamily;
    }

    public function isBreaking(): bool
    {
        if (! $this->occurred_at) {
            return false;
        }

        $window = (int) config('social.breaking.window_minutes', 60);

        return $this->occurred_at->isAfter(now()->subMinutes($window));
    }

    // ── Scopes ──

    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        $cutoff = now()->subHours($hours);

        return $query->where(function (Builder $q) use ($cutoff) {
            $q->where('occurred_at', '>=', $cutoff)
              ->orWhere(function (Builder $q2) use ($cutoff) {
                  $q2->whereNull('occurred_at')
                     ->where('created_at', '>=', $cutoff);
              });
        });
    }

    public function scopeByCountry(Builder $query, string $country): Builder
    {
        return $query->where('country', $country);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeWithConfidenceAbove(Builder $query, int $min): Builder
    {
        return $query->where('confidence', '>=', $min);
    }

    public function scopePendingClassification(Builder $query): Builder
    {
        return $query->where('status', 'pending_classification');
    }

    public function scopeNeedsReconciliation(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->subHours(48))
            ->where(function (Builder $q) {
                $q->whereNull('last_reconciled_at')
                  ->orWhere('last_reconciled_at', '<', now()->subMinutes(30));
            });
    }
}
