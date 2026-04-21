<?php

namespace App\Console\Commands;

use App\Services\Graph\RelationshipDerivationService;
use Illuminate\Console\Command;

class RebuildRelationshipsCommand extends Command
{
    protected $signature = 'relationships:rebuild';
    protected $description = 'Rebuild all derived relationships from the current domain data (actors, conflict_threads, entity_extractions).';

    public function handle(RelationshipDerivationService $service): int
    {
        $this->info('Rebuilding derived relationships…');
        $stats = $service->rebuild();

        $this->line("Deleted derived rows: {$stats['deleted']}");
        foreach ($stats['inserted'] as $group => $n) {
            $this->line("  {$group}: {$n}");
        }
        $this->info('Done.');

        return self::SUCCESS;
    }
}
