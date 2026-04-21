<?php

namespace App\Console\Commands;

use App\Services\Threading\ConflictLifecycleService;
use Illuminate\Console\Command;

class ManageConflictLifecycleCommand extends Command
{
    protected $signature = 'conflicts:lifecycle
                            {--close-only : Only close inactive threads}
                            {--merge-only : Only merge duplicate threads}
                            {--promote-only : Only promote emerging conflicts}
                            {--summaries-only : Only refresh stale summaries}';

    protected $description = 'Manage conflict thread lifecycle: close inactive, merge duplicates, promote emerging, refresh summaries';

    public function handle(ConflictLifecycleService $service): int
    {
        if ($this->option('close-only')) {
            $closed = $service->closeInactiveThreads();
            $this->info("Closed {$closed} inactive threads.");

            return self::SUCCESS;
        }

        if ($this->option('merge-only')) {
            $merged = $service->mergeDuplicateThreads();
            $this->info("Merged {$merged} duplicate threads.");

            return self::SUCCESS;
        }

        if ($this->option('promote-only')) {
            $promoted = $service->promoteEmergingConflicts();
            $this->info("Promoted {$promoted} emerging conflicts.");

            return self::SUCCESS;
        }

        if ($this->option('summaries-only')) {
            $updated = $service->refreshStaleSummaries();
            $this->info("Updated {$updated} thread summaries.");

            return self::SUCCESS;
        }

        // Full lifecycle run
        $this->info('Running full conflict lifecycle...');
        $results = $service->run();

        $this->table(
            ['Operation', 'Count'],
            [
                ['Inactive threads closed', $results['closed']],
                ['Duplicate threads merged', $results['merged']],
                ['Emerging conflicts promoted', $results['promoted']],
                ['Summaries refreshed', $results['summaries_updated']],
            ]
        );

        return self::SUCCESS;
    }
}
