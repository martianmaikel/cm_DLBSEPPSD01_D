<?php

namespace App\Jobs;

use App\Models\ActorCandidate;
use App\Models\EntityExtraction;
use App\Services\Actors\ActorResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResolveActorJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(private readonly string $eventId) {}

    public function handle(ActorResolver $resolver): void
    {
        $extractions = EntityExtraction::where('event_id', $this->eventId)
            ->whereIn('type', ['person', 'organization'])
            ->whereNull('actor_id')
            ->get();

        if ($extractions->isEmpty()) {
            return;
        }

        foreach ($extractions as $extraction) {
            $this->resolveOne($resolver, $extraction);
        }
    }

    private function resolveOne(ActorResolver $resolver, EntityExtraction $extraction): void
    {
        $name = $extraction->canonical_name ?: $extraction->name;
        $type = $extraction->type;

        $match = $resolver->matchActor($name, $type);

        if ($match) {
            $extraction->update(['actor_id' => $match->id]);

            // Add alias if unseen
            $normalized = $resolver->normalizeName($extraction->name);
            $existingAliases = array_map(
                fn ($a) => mb_strtolower((string) $a),
                $match->aliases ?? []
            );
            if (! in_array($normalized, $existingAliases, true)
                && $normalized !== mb_strtolower((string) $match->canonical_name)) {
                $match->aliases = array_values(array_unique(array_merge($match->aliases ?? [], [$extraction->name])));
            }

            $match->mention_count = (int) $match->mention_count + 1;
            $distinctEventCount = EntityExtraction::where('actor_id', $match->id)
                ->distinct('event_id')
                ->count('event_id');
            $match->event_count = $distinctEventCount;
            $match->last_mentioned_at = now();
            if (! $match->first_mentioned_at) {
                $match->first_mentioned_at = now();
            }
            $match->save();

            return;
        }

        $this->upsertCandidate($resolver, $extraction);
    }

    private function upsertCandidate(ActorResolver $resolver, EntityExtraction $extraction): void
    {
        $normalized = $resolver->normalizeName($extraction->canonical_name ?: $extraction->name);

        if ($normalized === '') {
            return;
        }

        $display = $extraction->canonical_name ?: $extraction->name;

        DB::transaction(function () use ($normalized, $extraction, $display) {
            $candidate = ActorCandidate::where('normalized_name', $normalized)
                ->where('actor_type', $extraction->type)
                ->lockForUpdate()
                ->first();

            if (! $candidate) {
                $candidate = new ActorCandidate([
                    'normalized_name' => $normalized,
                    'actor_type' => $extraction->type,
                    'display_name' => $display,
                    'mention_events_json' => [$extraction->event_id],
                    'event_count' => 1,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'blocked' => false,
                ]);
                $candidate->save();

                return;
            }

            $events = $candidate->mention_events_json ?? [];
            if (! in_array($extraction->event_id, $events, true)) {
                $events[] = $extraction->event_id;
                $candidate->mention_events_json = $events;
                $candidate->event_count = count($events);
            }
            $candidate->last_seen_at = now();
            $candidate->save();
        });

        Log::debug('ResolveActorJob: candidate upserted', [
            'normalized_name' => $normalized,
            'type' => $extraction->type,
        ]);
    }
}
