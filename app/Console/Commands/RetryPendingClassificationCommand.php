<?php

namespace App\Console\Commands;

use App\Jobs\ProcessRawEventJob;
use App\Models\Event;
use Illuminate\Console\Command;

class RetryPendingClassificationCommand extends Command
{
    protected $signature = 'events:retry-classification';
    protected $description = 'Re-dispatch ProcessRawEventJob for events stuck in pending_classification';

    private const MAX_ATTEMPTS = 5;
    private const MIN_AGE_MINUTES = 5;

    public function handle(): int
    {
        $events = Event::pendingClassification()
            ->where('classification_attempts', '<', self::MAX_ATTEMPTS)
            ->where('updated_at', '<', now()->subMinutes(self::MIN_AGE_MINUTES))
            ->get();

        if ($events->isEmpty()) {
            $this->info('No events pending retry.');

            return self::SUCCESS;
        }

        $this->info("Retrying classification for {$events->count()} event(s)...");

        foreach ($events as $event) {
            ProcessRawEventJob::dispatch([
                'title' => $event->title,
                'raw_content' => $event->raw_content,
                'source_id' => $event->source_id,
                'hash' => $event->hash,
                'occurred_at' => $event->occurred_at?->toDateTimeString() ?? now()->toDateTimeString(),
            ]);

            $this->line("  Queued: {$event->id} (attempt {$event->classification_attempts})");
        }

        return self::SUCCESS;
    }
}
