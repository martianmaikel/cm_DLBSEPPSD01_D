<?php

namespace App\Console\Commands;

use App\Models\ConflictThread;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateThreadStatsCommand extends Command
{
    protected $signature = 'threads:update-stats';

    protected $description = 'Refresh cached statistics on all conflict threads';

    public function handle(): int
    {
        $threads = ConflictThread::all();

        foreach ($threads as $thread) {
            // For top-level threads, include events from self + children
            if ($thread->parent_id === null) {
                $childIds = $thread->children()->pluck('id');
                $allThreadIds = $childIds->push($thread->id);

                $stats = Event::whereIn('conflict_thread_id', $allThreadIds)
                    ->whereNotIn('status', ['pending_classification', 'retracted'])
                    ->selectRaw("
                        COUNT(*) as total,
                        SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as last_24h,
                        MAX(severity) as max_sev
                    ", [now()->subHours(24)])
                    ->first();

                $countries = Event::whereIn('conflict_thread_id', $allThreadIds)
                    ->whereNotNull('country')
                    ->distinct()
                    ->pluck('country')
                    ->values()
                    ->toArray();

                $categories = Event::whereIn('conflict_thread_id', $allThreadIds)
                    ->whereNotNull('category')
                    ->distinct()
                    ->pluck('category')
                    ->values()
                    ->toArray();

                $thread->update([
                    'event_count_24h' => $stats->last_24h ?? 0,
                    'event_count_total' => $stats->total ?? 0,
                    'max_severity' => $stats->max_sev ?? 0,
                    'sub_thread_count' => $childIds->count(),
                    'countries' => $countries,
                    'categories' => $categories,
                ]);
            } else {
                // Sub-thread: only own events
                $stats = Event::where('conflict_thread_id', $thread->id)
                    ->whereNotIn('status', ['pending_classification', 'retracted'])
                    ->selectRaw("
                        COUNT(*) as total,
                        SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as last_24h,
                        MAX(severity) as max_sev
                    ", [now()->subHours(24)])
                    ->first();

                $countries = Event::where('conflict_thread_id', $thread->id)
                    ->whereNotNull('country')
                    ->distinct()
                    ->pluck('country')
                    ->values()
                    ->toArray();

                $categories = Event::where('conflict_thread_id', $thread->id)
                    ->whereNotNull('category')
                    ->distinct()
                    ->pluck('category')
                    ->values()
                    ->toArray();

                $thread->update([
                    'event_count_24h' => $stats->last_24h ?? 0,
                    'event_count_total' => $stats->total ?? 0,
                    'max_severity' => $stats->max_sev ?? 0,
                    'sub_thread_count' => 0,
                    'countries' => $countries,
                    'categories' => $categories,
                ]);
            }
        }

        $this->info("Updated stats for {$threads->count()} threads.");

        return self::SUCCESS;
    }
}
