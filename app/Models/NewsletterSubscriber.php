<?php

namespace App\Models;

use App\Models\ConflictThread;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'email',
        'timezone',
        'locale',
        'status',
        'confirm_token',
        'confirmed_at',
        'confirm_ip',
        'unsubscribe_token',
        'preferences_token',
        'wants_global_digest',
        'last_sent_at',
        'bounce_count',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'wants_global_digest' => 'boolean',
            'confirmed_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function sends(): HasMany
    {
        return $this->hasMany(NewsletterSend::class, 'subscriber_id');
    }

    public function threads(): BelongsToMany
    {
        return $this->belongsToMany(
            ConflictThread::class,
            'newsletter_subscriber_thread',
            'subscriber_id',
            'conflict_thread_id',
        )
            ->withPivot(['wants_digest', 'wants_critical'])
            ->withTimestamps();
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeByTimezone(Builder $query, string $timezone): Builder
    {
        return $query->where('timezone', $timezone);
    }

    /**
     * Normalize email to lowercase on set.
     */
    protected function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower(trim($value));
    }

    /**
     * Generate a fresh subscriber with all required tokens set.
     *
     * Explicitly sets boolean defaults to avoid in-memory NULL values
     * when DB defaults exist but aren't reflected on the returned model.
     */
    public static function createPending(array $attributes): self
    {
        return self::create(array_merge([
            'confirm_token' => Str::random(64),
            'unsubscribe_token' => Str::random(64),
            'preferences_token' => Str::random(64),
            'status' => 'pending',
            'wants_global_digest' => true,
        ], $attributes));
    }
}
