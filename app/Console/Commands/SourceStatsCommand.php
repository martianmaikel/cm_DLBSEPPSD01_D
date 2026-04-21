<?php

namespace App\Console\Commands;

use App\Models\Source;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class SourceStatsCommand extends Command
{
    protected $signature = 'sources:stats';
    protected $description = 'Show source quality stats from the last 24 hours (items processed, accepted, irrelevant, failed)';

    public function handle(): void
    {
        $sources = Source::where('active', true)->orderBy('name')->get();

        $rows = [];

        foreach ($sources as $source) {
            $key = "source_stats:{$source->id}";
            $stats = Redis::hgetall($key);

            if (empty($stats)) {
                continue;
            }

            $total = (int) ($stats['total'] ?? 0);
            $prefiltered = (int) ($stats['prefiltered'] ?? 0);
            $accepted = (int) ($stats['accepted'] ?? 0);
            $irrelevant = (int) ($stats['irrelevant'] ?? 0);
            $failed = (int) ($stats['failed'] ?? 0);
            $aiCalls = $total - $prefiltered;
            $ratio = $total > 0 ? round($accepted / $total * 100) : 0;

            $rows[] = [
                $source->name,
                $source->type,
                $total,
                $prefiltered,
                $aiCalls,
                $accepted,
                $irrelevant,
                $failed,
                $ratio . '%',
            ];
        }

        if (empty($rows)) {
            $this->info('No source stats recorded yet. Stats populate after ProcessRawEventJob runs.');

            return;
        }

        // Sort by acceptance ratio ascending (worst sources first)
        usort($rows, fn ($a, $b) => (int) $a[6] <=> (int) $b[6]);

        $this->table(
            ['Source', 'Type', 'Total', 'Pre-filter', 'AI Calls', 'Accepted', 'Irrelevant', 'Failed', 'Accept %'],
            $rows,
        );
    }
}
