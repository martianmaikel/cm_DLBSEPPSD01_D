<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Actor extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::saving(function (Actor $actor) {
            if (! $actor->slug && $actor->canonical_name) {
                $base = Str::slug(Str::limit($actor->canonical_name, 80, '')) ?: 'actor';
                $actor->slug = $actor->uniqueSlug($base);
            }
        });
    }

    protected $fillable = [
        'slug',
        'actor_type',
        'canonical_name',
        'aliases',
        'country',
        'region',
        'summary_short',
        'summary_long',
        'relevance_summary',
        'categories',
        'status',
        'confidence',
        'image_url',
        'sources_json',
        'locked_fields',
        'first_mentioned_at',
        'last_mentioned_at',
        'mention_count',
        'event_count',
        'enrichment_status',
        'enrichment_mode_used',
        'enriched_at',
        'promoted_at',
        'full_name',
        'role_title',
        'affiliation_actor_id',
        'birth_year',
        'death_year',
        'nationality',
        'org_type',
        'founded_year',
        'dissolved_year',
        'headquarters_country',
        'parent_actor_id',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'categories' => 'array',
            'sources_json' => 'array',
            'locked_fields' => 'array',
            'confidence' => 'integer',
            'mention_count' => 'integer',
            'event_count' => 'integer',
            'birth_year' => 'integer',
            'death_year' => 'integer',
            'founded_year' => 'integer',
            'dissolved_year' => 'integer',
            'first_mentioned_at' => 'datetime',
            'last_mentioned_at' => 'datetime',
            'enriched_at' => 'datetime',
            'promoted_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function entityExtractions(): HasMany
    {
        return $this->hasMany(EntityExtraction::class);
    }

    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'affiliation_actor_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'parent_actor_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Actor::class, 'affiliation_actor_id');
    }

    public function subUnits(): HasMany
    {
        return $this->hasMany(Actor::class, 'parent_actor_id');
    }

    // ── Scopes ──

    public function scopePersons(Builder $query): Builder
    {
        return $query->where('actor_type', 'person');
    }

    public function scopeOrganizations(Builder $query): Builder
    {
        return $query->where('actor_type', 'organization');
    }

    public function scopeEnriched(Builder $query): Builder
    {
        return $query->where('enrichment_status', 'enriched');
    }

    public function scopeStale(Builder $query, int $days): Builder
    {
        return $query
            ->where('enrichment_status', 'enriched')
            ->where('enriched_at', '<', now()->subDays($days));
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base;
        $n = 2;
        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }
        return $slug;
    }
}
