<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\Ingestion\ConnectorRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollSourcesCommand extends Command
{
    protected $signature = 'sources:poll {--type= : Only poll sources of this type} {--source= : Poll a specific source by ID}';
    protected $description = 'Poll all active sources that are due for ingestion';

    public function handle(ConnectorRegistry $registry): int
    {
        $query = Source::dueForPolling();

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        if ($sourceId = $this->option('source')) {
            $query = Source::where('id', $sourceId)->active();
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            $this->info('No sources due for polling.');

            return self::SUCCESS;
        }

        $this->info("Polling {$sources->count()} source(s)...");

        $succeeded = 0;
        $failed = 0;

        foreach ($sources as $source) {
            try {
                if (! $registry->has($source)) {
                    $this->warn("  Skipped: [{$source->type}] {$source->name} — no connector registered");

                    continue;
                }

                $connector = $registry->resolve($source);
                $connector->poll($source);

                $source->update(['last_polled_at' => now()]);

                $this->line("  Polled: [{$source->type}] {$source->name}");
                $succeeded++;
            } catch (\Throwable $e) {
                Log::error('PollSourcesCommand: error polling source', [
                    'source_id' => $source->id,
                    'source_name' => $source->name,
                    'error' => $e->getMessage(),
                ]);

                $this->error("  Failed: {$source->name} — {$e->getMessage()}");
                $failed++;

                // Mark last_polled_at anyway to prevent tight retry loops on broken sources
                $source->update(['last_polled_at' => now()]);
            }
        }

        $this->info("Done. Succeeded: {$succeeded}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
