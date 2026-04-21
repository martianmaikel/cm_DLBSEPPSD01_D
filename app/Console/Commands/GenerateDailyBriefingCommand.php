<?php

namespace App\Console\Commands;

use App\Services\Briefing\DailyBriefingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateDailyBriefingCommand extends Command
{
    protected $signature = 'briefing:generate
        {--date= : Specific date (Y-m-d), defaults to today}
        {--force : Regenerate even if briefing exists for this date}';

    protected $description = 'Generate the daily intelligence briefing using AI';

    public function handle(DailyBriefingService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::today();

        $force = (bool) $this->option('force');

        $this->info("Generating briefing for {$date->toDateString()}...");

        try {
            $briefing = $service->generate($date, $force);

            if ($briefing) {
                $this->info("Briefing generated: {$briefing->title}");
            } else {
                $this->warn('No briefing generated (no events found for this date).');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Briefing generation failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
