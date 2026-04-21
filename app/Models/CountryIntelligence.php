<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryIntelligence extends Model
{
    protected $table = 'country_intelligence';

    protected $primaryKey = 'country_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'country_code',
        'country_name',
        'continent_slug',
        'threat_level',
        'intelligence_briefing_en',
        'intelligence_briefing_de',
        'event_count_24h',
        'event_count_total',
        'max_severity',
        'avg_severity',
        'category_breakdown',
        'active_thread_ids',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'threat_level' => 'integer',
            'event_count_24h' => 'integer',
            'event_count_total' => 'integer',
            'max_severity' => 'integer',
            'avg_severity' => 'float',
            'category_breakdown' => 'array',
            'active_thread_ids' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
