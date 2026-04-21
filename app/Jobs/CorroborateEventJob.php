<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Corroboration\CorroborationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CorroborateEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(private readonly string $eventId) {}

    public function handle(CorroborationService $corroborationService): void
    {
        $event = Event::with(['source.sourceFamily', 'entityExtractions'])->find($this->eventId);

        if (! $event) {
            Log::warning('CorroborateEventJob: event not found', ['event_id' => $this->eventId]);

            return;
        }

        Log::debug('CorroborateEventJob: event loaded', ['event_id' => $event->id, 'status' => $event->status]);

        if ($event->status === 'pending_classification') {
            Log::debug('CorroborateEventJob: skipping pending_classification event', ['event_id' => $this->eventId]);

            return;
        }

        try {
            $corroborationService->findMatches($event);
        } catch (\Throwable $e) {
            Log::error('CorroborateEventJob failed', [
                'event_id' => $this->eventId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        AssignThreadJob::dispatch($this->eventId);
    }
}
