<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NewsletterAffiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'headline_en',
        'headline_de',
        'body_en',
        'body_de',
        'image_url',
        'target_url',
        'cta_en',
        'cta_de',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'weight',
        'active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'weight' => 'integer',
            'impression_count' => 'integer',
            'click_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (NewsletterAffiliate $a) {
            if (! $a->slug && $a->name) {
                $a->slug = Str::slug($a->name);
            }
        });
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(NewsletterAffiliateClick::class, 'affiliate_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->active()
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function getLocalizedHeadline(string $locale): string
    {
        return $locale === 'de' ? ($this->headline_de ?: $this->headline_en) : $this->headline_en;
    }

    public function getLocalizedBody(string $locale): ?string
    {
        return $locale === 'de' ? ($this->body_de ?: $this->body_en) : $this->body_en;
    }

    public function getLocalizedCta(string $locale): string
    {
        return $locale === 'de' ? ($this->cta_de ?: $this->cta_en) : $this->cta_en;
    }

    /**
     * Build the outgoing URL with UTM params appended.
     */
    public function buildTrackingUrl(): string
    {
        return url('/r/a/'.$this->slug);
    }
}
