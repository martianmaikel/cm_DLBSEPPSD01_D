<?php

namespace App\Console\Commands;

use App\Jobs\ReconciliationJob;
use Illuminate\Console\Command;

class ReconciliationCommand extends Command
{
    protected $signature = 'reconciliation:run';
    protected $description = 'Dispatch a reconciliation job to re-check corroboration for recent events';

    public function handle(): int
    {
        ReconciliationJob::dispatch();

        $this->info('ReconciliationJob dispatched.');

        return self::SUCCESS;
    }
}
