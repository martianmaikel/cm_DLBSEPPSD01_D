<?php

namespace App\Jobs;

use App\Models\Actor;
use App\Models\ActorCandidate;
use App\Models\EntityExtraction;
use App\Services\Actors\ActorResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PromoteActorCandidatesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 300;

    public function handle(ActorResolver $resolver): void
    {
        $threshold = (int) config('actors.promotion_threshold', 3);

        $candidates = ActorCandidate::readyToPromote($threshold)->get();

        if ($candidates->isEmpty()) {
            Log::debug('PromoteActorCandidatesJob: no candidates ready');
            return;
        }

        foreach ($candidates as $candidate) {
            try {
                $this->promote($candidate, $resolver);
            } catch (\Throwable $e) {
                Log::error('PromoteActorCandidatesJob: promotion failed', [
                    'candidate_id' => $candidate->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function promote(ActorCandidate $candidate, ActorResolver $resolver): void
    {
        // Guard: an actor with the same normalized canonical name and type may have been
        // created in the meantime (e.g. via another candidate or manual admin). Merge then.
        $existing = $resolver->matchActor($candidate->display_name, $candidate->actor_type);

        $actorId = DB::transaction(function () use ($candidate, $existing) {
            if ($existing) {
                $actor = $existing;
            } else {
                $actor = Actor::create([
                    'actor_type' => $candidate->actor_type,
                    'canonical_name' => $candidate->display_name,
                    'aliases' => [],
                    'status' => 'active',
                    'enrichment_status' => 'pending',
                    'promoted_at' => now(),
                    'first_mentioned_at' => $candidate->first_seen_at,
                    'last_mentioned_at' => $candidate->last_seen_at,
                ]);
            }

            // Backfill all entity_extractions for this candidate's events+type whose
            // actor_id is still null and whose name matches the candidate.
            $eventIds = $candidate->mention_events_json ?? [];
            if (! empty($eventIds)) {
                EntityExtraction::whereIn('event_id', $eventIds)
                    ->where('type', $candidate->actor_type)
                    ->whereNull('actor_id')
                    ->update(['actor_id' => $actor->id]);
            }

            $distinct = EntityExtraction::where('actor_id', $actor->id)->distinct('event_id')->count('event_id');
            $mentions = EntityExtraction::where('actor_id', $actor->id)->count();

            $actor->event_count = $distinct;
            $actor->mention_count = $mentions;
            if (! $actor->first_mentioned_at) {
                $actor->first_mentioned_at = $candidate->first_seen_at;
            }
            $actor->last_mentioned_at = $candidate->last_seen_at ?? now();
            $actor->save();

            $candidate->delete();

            return $actor->id;
        });

        EnrichActorJob::dispatch($actorId);

        Log::info('Actor promoted', [
            'actor_id' => $actorId,
            'candidate_name' => $candidate->display_name,
            'event_count' => $candidate->event_count,
        ]);
    }
}
