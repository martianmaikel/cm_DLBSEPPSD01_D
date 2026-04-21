<?php

namespace App\Jobs;

use App\Services\Graph\RelationshipDerivationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RebuildDerivedRelationshipsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 600;

    public function handle(RelationshipDerivationService $service): void
    {
        $service->rebuild();
    }
}
