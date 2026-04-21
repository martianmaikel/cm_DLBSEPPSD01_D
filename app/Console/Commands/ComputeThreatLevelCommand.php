<?php

namespace App\Console\Commands;

use App\Services\ThreatLevel\WorldThreatLevelService;
use Illuminate\Console\Command;

class ComputeThreatLevelCommand extends Command
{
    protected $signature = 'threat-level:compute
        {--force : Regenerate LLM summary even if cached}';

    protected $description = 'Compute the current world threat level with AI summary';

    public function handle(WorldThreatLevelService $service): int
    {
        $force = (bool) $this->option('force');

        $this->info('Computing world threat level...');

        try {
            $result = $service->compute($force);

            $this->info("Threat Level: {$result['level']}/10 — {$result['label_en']}");

            if ($result['summary_en']) {
                $this->line("EN: {$result['summary_en']}");
            } else {
                $this->warn('No AI summary generated (score-only mode).');
            }

            $stats = $result['statistics'];
            $this->line("Events: {$stats['total_events']} | Zones: {$stats['active_zones']} | Trend: {$stats['escalation_trend']}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Threat level computation failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
