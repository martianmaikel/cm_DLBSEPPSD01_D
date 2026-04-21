<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityExtraction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'name',
        'canonical_name',
        'role_context',
        'type',
        'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }
}
