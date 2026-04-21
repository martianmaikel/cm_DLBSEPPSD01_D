<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ConflictThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'summary',
        'status',
        'countries',
        'categories',
        'hashtags',
        'event_count_24h',
        'event_count_total',
        'max_severity',
        'sub_thread_count',
    ];

    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'categories' => 'array',
            'hashtags' => 'array',
            'event_count_24h' => 'integer',
            'event_count_total' => 'integer',
            'max_severity' => 'integer',
            'sub_thread_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ConflictThread $thread) {
            if (! $thread->slug && $thread->name) {
                $thread->slug = Str::slug($thread->name);
            }
        });
    }

    // ── Relationships ──

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get all events from this thread and all its sub-threads.
     */
    public function allEvents(): Builder
    {
        $threadIds = $this->children()->pluck('id')->push($this->id);

        return Event::whereIn('conflict_thread_id', $threadIds);
    }

    // ── Scopes ──

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeSubThreads(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }
}
