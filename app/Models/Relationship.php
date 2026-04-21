<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Relationship extends Model
{
    public const TYPES_NODES = ['actor', 'country', 'conflict', 'event'];
    public const SOURCES = ['derived', 'manual', 'ai'];

    /**
     * All supported edge types. Grouped by endpoint pair for UI building.
     *
     * @var array<string, array<int, string>>
     */
    public const RELATION_TYPES = [
        'actor.country' => ['member_of_country', 'based_in', 'heads_country', 'represents'],
        'actor.actor' => ['affiliated_with', 'subunit_of', 'allied_with', 'opposed_to', 'commands', 'spokesperson_for'],
        'actor.conflict' => ['party_to', 'commander_in', 'mediator_in'],
        'country.country' => ['at_war_with', 'allied_with', 'sanctions_on', 'tension_with', 'peace_treaty_with'],
        'country.conflict' => ['party_to', 'host_of'],
        'conflict.conflict' => ['part_of', 'spawned_from'],
    ];

    protected $fillable = [
        'from_type', 'from_id',
        'to_type', 'to_id',
        'relation_type',
        'directed', 'weight', 'source',
        'evidence_json', 'active_from', 'active_to', 'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'directed' => 'boolean',
            'weight' => 'float',
            'evidence_json' => 'array',
            'metadata_json' => 'array',
            'active_from' => 'datetime',
            'active_to' => 'datetime',
        ];
    }

    public function scopeBetween(Builder $q, string $fromType, string $fromId, string $toType, string $toId): Builder
    {
        return $q->where([
            'from_type' => $fromType,
            'from_id' => $fromId,
            'to_type' => $toType,
            'to_id' => $toId,
        ]);
    }

    public function scopeFrom(Builder $q, string $type, string $id): Builder
    {
        return $q->where('from_type', $type)->where('from_id', $id);
    }

    public function scopeTo(Builder $q, string $type, string $id): Builder
    {
        return $q->where('to_type', $type)->where('to_id', $id);
    }

    public function scopeTouching(Builder $q, string $type, string $id): Builder
    {
        return $q->where(function (Builder $qq) use ($type, $id) {
            $qq->where(fn ($s) => $s->where('from_type', $type)->where('from_id', $id))
               ->orWhere(fn ($s) => $s->where('to_type', $type)->where('to_id', $id));
        });
    }

    public function scopeManual(Builder $q): Builder
    {
        return $q->where('source', 'manual');
    }

    public function scopeDerived(Builder $q): Builder
    {
        return $q->where('source', 'derived');
    }

    /**
     * Returns true if any manual/ai row exists that would override this derived edge.
     * Used by the derivation service before inserting to respect admin overrides.
     */
    public static function hasOverride(string $fromType, string $fromId, string $toType, string $toId, string $relationType): bool
    {
        return static::query()
            ->whereIn('source', ['manual', 'ai'])
            ->where('from_type', $fromType)->where('from_id', $fromId)
            ->where('to_type', $toType)->where('to_id', $toId)
            ->where('relation_type', $relationType)
            ->exists();
    }
}
