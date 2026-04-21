<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Corroboration\CorroborationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ReconciliationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 300;

    private const LAST_RUN_KEY = 'reconciliation:last_run';

    public function handle(CorroborationService $corroborationService): void
    {
        $lastRunTimestamp = Redis::get(self::LAST_RUN_KEY);
        $lastRun = $lastRunTimestamp ? \Illuminate\Support\Carbon::createFromTimestamp((int) $lastRunTimestamp) : now()->subMinutes(30);

        Log::info('ReconciliationJob started', ['last_run' => $lastRun->toIso8601String()]);

        // Scope: events created since last run, within the 48h corroboration window
        $events = Event::query()
            ->where('created_at', '>=', $lastRun)
            ->where('created_at', '>=', now()->subHours(48))
            ->where('status', '!=', 'pending_classification')
            ->with(['source.sourceFamily', 'entityExtractions'])
            ->get();

        $processed = 0;

        foreach ($events as $event) {
            try {
                $corroborationService->findMatches($event);
                $event->update(['last_reconciled_at' => now()]);

                // If the event still has no thread, queue a thread-assignment pass.
                // The normal pipeline runs this via CorroborateEventJob, but events that
                // reach status updates only through reconciliation would otherwise stay
                // unassigned.
                if ($event->conflict_thread_id === null) {
                    AssignThreadJob::dispatch($event->id);
                }

                $processed++;
            } catch (\Throwable $e) {
                Log::warning('ReconciliationJob: failed for event', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Redis::set(self::LAST_RUN_KEY, now()->timestamp);

        Log::info('ReconciliationJob completed', [
            'processed' => $processed,
            'total' => $events->count(),
        ]);
    }
}
