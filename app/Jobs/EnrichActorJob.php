<?php

namespace App\Jobs;

use App\Models\Actor;
use App\Services\Actors\ActorEnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnrichActorJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [30, 120, 300];

    public function __construct(private readonly string $actorId) {}

    public function handle(ActorEnrichmentService $service): void
    {
        $actor = Actor::find($this->actorId);

        if (! $actor) {
            Log::warning('EnrichActorJob: actor not found', ['actor_id' => $this->actorId]);
            return;
        }

        $actor->update(['enrichment_status' => 'enriching']);

        try {
            $service->enrich($actor->fresh());
        } catch (\Throwable $e) {
            $actor->update(['enrichment_status' => 'failed']);
            Log::error('EnrichActorJob: enrichment failed', [
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
