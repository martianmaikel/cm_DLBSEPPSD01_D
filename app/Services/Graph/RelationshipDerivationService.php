<?php

namespace App\Services\Graph;

use App\Models\Actor;
use App\Models\ConflictThread;
use App\Models\EntityExtraction;
use App\Models\Relationship;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RelationshipDerivationService
{
    private const ACTOR_PARTY_TO_MIN_EVENTS = 3;

    public function rebuild(): array
    {
        $stats = [
            'deleted' => 0,
            'inserted' => [],
        ];

        DB::transaction(function () use (&$stats) {
            $stats['deleted'] = Relationship::derived()->delete();

            $stats['inserted']['actor_country'] = $this->deriveActorCountry();
            $stats['inserted']['actor_actor'] = $this->deriveActorActor();
            $stats['inserted']['country_conflict'] = $this->deriveCountryConflict();
            $stats['inserted']['country_country_war'] = $this->deriveCountryCountryAtWar();
            $stats['inserted']['actor_conflict'] = $this->deriveActorConflictParty();
            $stats['inserted']['conflict_conflict'] = $this->deriveConflictHierarchy();
        });

        Log::info('RelationshipDerivationService rebuild complete', $stats);

        return $stats;
    }

    private function deriveActorCountry(): int
    {
        $count = 0;

        Actor::query()
            ->select('id', 'nationality', 'country', 'headquarters_country', 'actor_type')
            ->whereNotNull('nationality')
            ->orWhereNotNull('country')
            ->orWhereNotNull('headquarters_country')
            ->chunkById(500, function ($actors) use (&$count) {
                foreach ($actors as $actor) {
                    if ($actor->nationality) {
                        $count += $this->upsertDerived('actor', $actor->id, 'country', $actor->nationality, 'member_of_country', true);
                    }
                    if ($actor->country) {
                        $count += $this->upsertDerived('actor', $actor->id, 'country', $actor->country, 'based_in', true);
                    }
                    if ($actor->headquarters_country && $actor->headquarters_country !== $actor->country) {
                        $count += $this->upsertDerived('actor', $actor->id, 'country', $actor->headquarters_country, 'based_in', true);
                    }
                }
            });

        return $count;
    }

    private function deriveActorActor(): int
    {
        $count = 0;

        Actor::query()
            ->select('id', 'affiliation_actor_id', 'parent_actor_id')
            ->where(function ($q) {
                $q->whereNotNull('affiliation_actor_id')->orWhereNotNull('parent_actor_id');
            })
            ->chunkById(500, function ($actors) use (&$count) {
                foreach ($actors as $actor) {
                    if ($actor->affiliation_actor_id) {
                        $count += $this->upsertDerived('actor', $actor->id, 'actor', $actor->affiliation_actor_id, 'affiliated_with', true);
                    }
                    if ($actor->parent_actor_id) {
                        $count += $this->upsertDerived('actor', $actor->id, 'actor', $actor->parent_actor_id, 'subunit_of', true);
                    }
                }
            });

        return $count;
    }

    private function deriveCountryConflict(): int
    {
        $count = 0;

        ConflictThread::query()
            ->select('id', 'countries')
            ->whereNotNull('countries')
            ->chunkById(200, function ($threads) use (&$count) {
                foreach ($threads as $thread) {
                    $countries = is_array($thread->countries) ? $thread->countries : [];
                    foreach (array_unique(array_filter($countries)) as $iso) {
                        $iso = strtoupper(trim((string) $iso));
                        if (preg_match('/^[A-Z]{2}$/', $iso)) {
                            $count += $this->upsertDerived('country', $iso, 'conflict', (string) $thread->id, 'party_to', true, weight: 1.0);
                        }
                    }
                }
            });

        return $count;
    }

    private function deriveCountryCountryAtWar(): int
    {
        $count = 0;

        ConflictThread::query()
            ->select('id', 'countries', 'categories')
            ->whereNotNull('countries')
            ->whereNotNull('categories')
            ->chunkById(200, function ($threads) use (&$count) {
                foreach ($threads as $thread) {
                    $cats = is_array($thread->categories) ? $thread->categories : [];
                    if (! in_array('war', array_map('strtolower', $cats), true)) {
                        continue;
                    }
                    $countries = array_values(array_unique(array_filter(array_map(
                        fn ($iso) => preg_match('/^[A-Z]{2}$/', strtoupper(trim((string) $iso))) ? strtoupper(trim((string) $iso)) : null,
                        is_array($thread->countries) ? $thread->countries : []
                    ))));

                    if (count($countries) < 2) {
                        continue;
                    }

                    // Pair-dedup: smaller ISO first, bidir edge represented once
                    sort($countries);
                    for ($i = 0; $i < count($countries); $i++) {
                        for ($j = $i + 1; $j < count($countries); $j++) {
                            $count += $this->upsertDerived(
                                'country', $countries[$i],
                                'country', $countries[$j],
                                'at_war_with',
                                directed: false,
                                weight: 0.6,
                                evidence: ['conflict_thread_ids' => [$thread->id]],
                            );
                        }
                    }
                }
            });

        return $count;
    }

    private function deriveActorConflictParty(): int
    {
        $rows = DB::table('entity_extractions as ee')
            ->join('events as e', 'e.id', '=', 'ee.event_id')
            ->whereNotNull('ee.actor_id')
            ->whereNotNull('e.conflict_thread_id')
            ->groupBy('ee.actor_id', 'e.conflict_thread_id')
            ->select(
                'ee.actor_id',
                'e.conflict_thread_id',
                DB::raw('COUNT(DISTINCT e.id) AS event_count'),
            )
            ->havingRaw('COUNT(DISTINCT e.id) >= ?', [self::ACTOR_PARTY_TO_MIN_EVENTS])
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            $weight = max(0.1, min(1.0, $row->event_count / 20));
            $count += $this->upsertDerived(
                'actor', (string) $row->actor_id,
                'conflict', (string) $row->conflict_thread_id,
                'party_to',
                directed: true,
                weight: $weight,
                evidence: ['event_count' => (int) $row->event_count],
            );
        }

        return $count;
    }

    private function deriveConflictHierarchy(): int
    {
        $count = 0;

        ConflictThread::query()
            ->select('id', 'parent_id')
            ->whereNotNull('parent_id')
            ->chunkById(500, function ($threads) use (&$count) {
                foreach ($threads as $thread) {
                    $count += $this->upsertDerived(
                        'conflict', (string) $thread->id,
                        'conflict', (string) $thread->parent_id,
                        'part_of',
                        directed: true,
                    );
                }
            });

        return $count;
    }

    private function upsertDerived(
        string $fromType, string $fromId,
        string $toType, string $toId,
        string $relationType,
        bool $directed = true,
        ?float $weight = null,
        ?array $evidence = null,
    ): int {
        if ($fromType === $toType && $fromId === $toId) {
            return 0;
        }

        // Respect manual/ai overrides — they define the "truth" for this edge
        if (Relationship::hasOverride($fromType, $fromId, $toType, $toId, $relationType)) {
            return 0;
        }

        Relationship::updateOrCreate(
            [
                'from_type' => $fromType,
                'from_id' => $fromId,
                'to_type' => $toType,
                'to_id' => $toId,
                'relation_type' => $relationType,
                'source' => 'derived',
            ],
            [
                'directed' => $directed,
                'weight' => $weight,
                'evidence_json' => $evidence,
            ],
        );

        return 1;
    }
}
