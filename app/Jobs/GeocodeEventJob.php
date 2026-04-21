<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Geocoding\NominatimService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeocodeEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 15;
    public int $maxExceptions = 3;
    public int $timeout = 30;
    public array $backoff = [5, 15, 30];

    public function __construct(
        private readonly string $eventId,
        private readonly ?float $llmLatitude = null,
        private readonly ?float $llmLongitude = null,
    ) {
        $this->queue = 'geocoding';
    }

    public function middleware(): array
    {
        return [new RateLimited('nominatim')];
    }

    public function handle(NominatimService $geocoder): void
    {
        $event = Event::find($this->eventId);

        if (! $event) {
            Log::warning('GeocodeEventJob: event not found, skipping pipeline', ['event_id' => $this->eventId]);

            return;
        }

        // Use LLM-provided coordinates if available (already extracted during classification)
        if ($this->llmLatitude !== null && $this->llmLongitude !== null) {
            $this->storeCoordinates($event, $this->llmLatitude, $this->llmLongitude, approximate: false);
            $this->dispatchCorroboration();

            return;
        }

        // Fall back to Nominatim geocoding using location strings
        $locationString = $this->buildLocationString($event);

        if (empty($locationString)) {
            $event->update(['geo_approximate' => true]);
            $this->dispatchCorroboration();

            return;
        }

        try {
            $coords = $geocoder->geocode($locationString);

            if ($coords !== null) {
                [$lat, $lng] = $coords;
                $this->storeCoordinates($event, $lat, $lng, approximate: false);
            } else {
                $event->update(['geo_approximate' => true]);
                Log::info('GeocodeEventJob: no coordinates found', [
                    'event_id' => $event->id,
                    'location' => $locationString,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('GeocodeEventJob: geocoding failed, proceeding', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            $event->update(['geo_approximate' => true]);
        }

        $this->dispatchCorroboration();
    }

    private function storeCoordinates(Event $event, float $lat, float $lng, bool $approximate): void
    {
        DB::statement(
            "UPDATE events SET coordinates = ST_SetSRID(ST_MakePoint(?, ?), 4326), geo_approximate = ? WHERE id = ?",
            [$lng, $lat, $approximate ? 1 : 0, $event->id]
        );
    }

    private function buildLocationString(Event $event): string
    {
        $parts = array_filter([$event->region, $event->country]);

        return implode(', ', $parts);
    }

    private function dispatchCorroboration(): void
    {
        CorroborateEventJob::dispatch($this->eventId);
    }
}
