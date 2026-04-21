<?php

namespace App\Console\Commands;

use App\Jobs\GeocodeEventJob;
use App\Models\Event;
use Illuminate\Console\Command;

class RegeocodeEventsCommand extends Command
{
    protected $signature = 'events:regeocode
                            {--hours=24 : Only process events created in the last N hours (0 = all)}';

    protected $description = 'Dispatch GeocodeEventJob for events that still have no coordinates';

    public function handle(): int
    {
        $query = Event::query()
            ->whereNull('coordinates')
            ->whereNotIn('status', ['pending_classification', 'retracted']);

        $hours = (int) $this->option('hours');
        if ($hours > 0) {
            $query->where('created_at', '>=', now()->subHours($hours));
        }

        $events = $query->orderBy('created_at')->get(['id']);

        if ($events->isEmpty()) {
            $this->info('No events without coordinates found.');

            return self::SUCCESS;
        }

        $this->info("Dispatching GeocodeEventJob for {$events->count()} events...");

        foreach ($events as $event) {
            GeocodeEventJob::dispatch($event->id);
        }

        $this->info('Done. Jobs queued on the "geocoding" queue (Nominatim rate-limited).');

        return self::SUCCESS;
    }
}
