<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Threading\ThreadMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AssignThreadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(private readonly string $eventId) {}

    public function handle(ThreadMatchingService $threadMatcher): void
    {
        $event = Event::find($this->eventId);

        if (! $event) {
            // Debug: raw SQL check to understand why Eloquent can't find it
            $rawCheck = \Illuminate\Support\Facades\DB::select(
                'SELECT id, status, hash FROM events WHERE id = ? LIMIT 1',
                [$this->eventId]
            );
            $totalEvents = \Illuminate\Support\Facades\DB::selectOne('SELECT count(*) as cnt FROM events');

            Log::warning('AssignThreadJob: event not found', [
                'event_id' => $this->eventId,
                'raw_exists' => ! empty($rawCheck),
                'raw_data' => $rawCheck[0] ?? null,
                'total_events' => $totalEvents->cnt ?? 0,
                'id_type' => gettype($this->eventId),
            ]);

            return;
        }

        try {
            $threadMatcher->assignThread($event);
        } catch (\Throwable $e) {
            Log::error('AssignThreadJob failed', [
                'event_id' => $this->eventId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // Critical alert dispatch for SEV≥9 events in assigned threads.
        // The dispatch job applies its own gates (status, severity, debounce, daily cap).
        $event->refresh();
        if ((int) $event->severity >= 9 && $event->conflict_thread_id) {
            DispatchCriticalAlertJob::dispatch($event->id)->afterCommit();
        }

        // Social media posting (the job applies its own relevance gates)
        EvaluateEventForSocialPostingJob::dispatch($event->id)->afterCommit();
    }
}
