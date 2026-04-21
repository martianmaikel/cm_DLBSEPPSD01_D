<?php

namespace App\Services\Geocoding;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NominatimService
{
    private const BASE_URL = 'https://nominatim.openstreetmap.org/search';
    private const USER_AGENT = 'ClashMonitor/1.0';

    public function geocode(string $location): ?array
    {
        if (empty(trim($location))) {
            return null;
        }

        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->timeout(10)
                ->get(self::BASE_URL, [
                    'q' => $location,
                    'format' => 'json',
                    'limit' => 1,
                    'addressdetails' => 0,
                ]);

            if ($response->failed()) {
                Log::warning('Nominatim geocode failed', [
                    'location' => $location,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $results = $response->json();

            if (empty($results) || ! is_array($results)) {
                return null;
            }

            $first = $results[0];

            $lat = isset($first['lat']) ? (float) $first['lat'] : null;
            $lng = isset($first['lon']) ? (float) $first['lon'] : null;

            if ($lat === null || $lng === null) {
                return null;
            }

            return [$lat, $lng];
        } catch (ConnectionException $e) {
            Log::warning('Nominatim connection failed', [
                'location' => $location,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('Nominatim unexpected error', [
                'location' => $location,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
