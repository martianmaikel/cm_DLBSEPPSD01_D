<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DailyBriefing extends Model
{
    use HasUuids;

    protected $fillable = [
        'briefing_date',
        'title',
        'summary_en',
        'summary_de',
        'key_developments',
        'conflict_sections',
        'statistics',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'briefing_date' => 'date:Y-m-d',
            'key_developments' => 'array',
            'conflict_sections' => 'array',
            'statistics' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderByDesc('briefing_date');
    }

    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('briefing_date', $date);
    }
}
