<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSend extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscriber_id',
        'type',
        'send_key',
        'subject',
        'locale',
        'affiliate_id',
        'ses_message_id',
        'status',
        'error',
        'sent_at',
        'opened_at',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'subscriber_id');
    }
}
