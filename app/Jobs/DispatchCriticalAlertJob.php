<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\NewsletterSend;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DispatchCriticalAlertJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 30;

    /**
     * Global pause cache key — admin can flip to halt all critical alerts.
     */
    public const string PAUSE_CACHE_KEY = 'newsletter.critical_alerts_paused';

    /**
     * Per subscriber+thread debounce window in seconds.
     */
    public const int THREAD_DEBOUNCE_SECONDS = 1800; // 30 minutes

    /**
     * Maximum critical alerts per subscriber per local day.
     */
    public const int DAILY_CAP = 5;

    public function __construct(public string $eventId)
    {
        $this->onQueue('newsletter');
    }

    public function handle(): void
    {
        // 1. Global pause check
        if (Cache::get(self::PAUSE_CACHE_KEY) === true) {
            Log::info('DispatchCriticalAlertJob: paused globally, skipping', ['event_id' => $this->eventId]);
            return;
        }

        // 2. Load event and validate gates
        $event = Event::find($this->eventId);
        if (! $event) {
            return;
        }

        if ((int) $event->severity < 9) {
            return;
        }

        if (! in_array($event->status, ['corroborated', 'confirmed'], true)) {
            return;
        }

        if (! $event->conflict_thread_id) {
            return;
        }

        // 3. Find subscribers who want critical alerts for this thread
        $subscribers = NewsletterSubscriber::query()
            ->confirmed()
            ->whereHas('threads', function ($q) use ($event) {
                $q->where('conflict_threads.id', $event->conflict_thread_id)
                  ->where('newsletter_subscriber_thread.wants_critical', true);
            })
            ->get();

        if ($subscribers->isEmpty()) {
            return;
        }

        $dispatched = 0;
        $skippedDebounce = 0;
        $skippedDailyCap = 0;

        foreach ($subscribers as $subscriber) {
            // 4a. Per-thread debounce (30 min cache)
            $debounceKey = "critical_alert:sub:{$subscriber->id}:thread:{$event->conflict_thread_id}";
            if (Cache::get($debounceKey) === true) {
                $skippedDebounce++;
                continue;
            }

            // 4b. Daily cap (5 per subscriber per local day)
            if ($this->isOverDailyCap($subscriber)) {
                $skippedDailyCap++;
                continue;
            }

            // 5. Dispatch and set debounce
            SendCriticalAlertJob::dispatch($subscriber->id, $event->id);
            Cache::put($debounceKey, true, self::THREAD_DEBOUNCE_SECONDS);
            $dispatched++;
        }

        Log::info('DispatchCriticalAlertJob: fan-out complete', [
            'event_id' => $event->id,
            'thread_id' => $event->conflict_thread_id,
            'severity' => $event->severity,
            'dispatched' => $dispatched,
            'skipped_debounce' => $skippedDebounce,
            'skipped_daily_cap' => $skippedDailyCap,
        ]);
    }

    private function isOverDailyCap(NewsletterSubscriber $subscriber): bool
    {
        $localMidnight = Carbon::now($subscriber->timezone)->startOfDay()->utc();

        $count = NewsletterSend::query()
            ->where('subscriber_id', $subscriber->id)
            ->where('type', 'critical_alert')
            ->where('sent_at', '>=', $localMidnight)
            ->count();

        return $count >= self::DAILY_CAP;
    }
}
