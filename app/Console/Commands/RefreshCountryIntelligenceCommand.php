<?php

namespace App\Console\Commands;

use App\Services\Intelligence\CountryIntelligenceService;
use Illuminate\Console\Command;

class RefreshCountryIntelligenceCommand extends Command
{
    protected $signature = 'intelligence:refresh-countries';

    protected $description = 'Refresh country intelligence data including threat levels and AI briefings';

    public function handle(CountryIntelligenceService $service): int
    {
        $this->info('Refreshing country intelligence...');

        $count = $service->refreshAll();

        $this->info("Refreshed {$count} countries.");

        return self::SUCCESS;
    }
}
