<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SocialPost extends Model
{
    protected $fillable = [
        'social_channel_id',
        'postable_type',
        'postable_id',
        'post_key',
        'platform',
        'locale',
        'content_text',
        'platform_post_id',
        'status',
        'error',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function socialChannel(): BelongsTo
    {
        return $this->belongsTo(SocialChannel::class);
    }

    public function postable(): MorphTo
    {
        return $this->morphTo();
    }
}
