<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Threading\ThreadMatchingService;
use Illuminate\Console\Command;

class ReassignThreadsCommand extends Command
{
    protected $signature = 'events:reassign-threads
                            {--unassigned-only : Only process events without a thread assignment}
                            {--hours=0 : Limit to events from the last N hours (0 = all)}';

    protected $description = 'Re-run thread assignment for existing events using the improved matching algorithm';

    public function handle(ThreadMatchingService $threadMatcher): int
    {
        $query = Event::query()
            ->whereNotIn('status', ['pending_classification', 'retracted']);

        if ($this->option('unassigned-only')) {
            $query->whereNull('conflict_thread_id');
        } else {
            // Reset thread assignments so they can be re-evaluated
            $query->whereNotNull('conflict_thread_id')
                ->update(['conflict_thread_id' => null]);

            // Refresh query for processing
            $query = Event::query()
                ->whereNotIn('status', ['pending_classification', 'retracted']);
        }

        $hours = (int) $this->option('hours');
        if ($hours > 0) {
            $query->where('created_at', '>=', now()->subHours($hours));
        }

        $events = $query->orderBy('occurred_at')->get();

        $this->info("Processing {$events->count()} events...");
        $bar = $this->output->createProgressBar($events->count());

        $assigned = 0;
        $created = 0;
        $skipped = 0;

        foreach ($events as $event) {
            $beforeThreadId = $event->conflict_thread_id;

            // Clear thread assignment so assignThread() will re-evaluate
            if ($beforeThreadId !== null) {
                $event->update(['conflict_thread_id' => null]);
            }

            $threadMatcher->assignThread($event->fresh());
            $event->refresh();

            if ($event->conflict_thread_id !== null && $event->conflict_thread_id !== $beforeThreadId) {
                $assigned++;
            } elseif ($event->conflict_thread_id === null) {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Done. Assigned: {$assigned}, Still unassigned: {$skipped}");

        return self::SUCCESS;
    }
}
