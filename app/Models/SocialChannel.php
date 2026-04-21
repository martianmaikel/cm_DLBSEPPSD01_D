<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialChannel extends Model
{
    protected $fillable = [
        'platform',
        'locale',
        'name',
        'handle',
        'credentials',
        'token_expires_at',
        'posts_event',
        'posts_briefing',
        'enabled',
        'unlimited_chars',
        'daily_post_count',
        'daily_post_limit',
        'min_post_interval',
        'last_posted_at',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'token_expires_at' => 'datetime',
            'posts_event' => 'boolean',
            'posts_briefing' => 'boolean',
            'enabled' => 'boolean',
            'unlimited_chars' => 'boolean',
            'daily_post_count' => 'integer',
            'daily_post_limit' => 'integer',
            'min_post_interval' => 'integer',
            'last_posted_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function socialPosts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    // ── Scopes ──

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    public function scopePostsEvents(Builder $query): Builder
    {
        return $query->where('posts_event', true);
    }

    public function scopePostsBriefings(Builder $query): Builder
    {
        return $query->where('posts_briefing', true);
    }

    // ── Helpers ──

    public function isUnderDailyLimit(): bool
    {
        return $this->daily_post_count < $this->daily_post_limit;
    }

    public function incrementPostCount(): void
    {
        $this->increment('daily_post_count');
        $this->update(['last_posted_at' => now()]);
    }
}
