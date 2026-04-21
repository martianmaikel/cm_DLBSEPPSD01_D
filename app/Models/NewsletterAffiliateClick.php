<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterAffiliateClick extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'affiliate_id',
        'send_id',
        'subscriber_id',
        'ip',
        'user_agent',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(NewsletterAffiliate::class, 'affiliate_id');
    }
}
