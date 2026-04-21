<?php

namespace App\Services\Ingestion\ApiConnectors;

use App\Contracts\SourceConnector;
use App\Jobs\ProcessRawEventJob;
use App\Models\Source;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * ACLED (Armed Conflict Location & Event Data) connector.
 *
 * Uses OAuth 2.0 authentication (access token + refresh token).
 * Register at: https://developer.acleddata.com/
 *
 * connector_config options:
 *   - email: registered email (or set ACLED_EMAIL in .env)
 *   - password: account password (or set ACLED_PASSWORD in .env)
 *   - limit: records per page (default: 5000, ACLED's max)
 *   - days_back: how many days to look back (default: 1)
 *   - max_pages: pagination safety limit (default: 10)
 *   - country: filter by country name, pipe-separated for multiple (optional)
 *   - event_type: filter by event type (optional)
 */
class AcledConnector implements SourceConnector
{
    private const API_BASE = 'https://acleddata.com/api/acled/read';

    private const TOKEN_URL = 'https://acleddata.com/oauth/token';

    // Only request the fields we actually need
    private const FIELDS = 'event_id_cnty|event_date|event_type|sub_event_type|actor1|actor2|country|admin1|location|latitude|longitude|fatalities|notes|source';

    public function supports(Source $source): bool
    {
        return $source->connector_class === self::class;
    }

    public function poll(Source $source): void
    {
        $config = $source->connector_config ?? [];
        $accessToken = $this->getAccessToken($source);

        if (! $accessToken) {
            Log::warning('ACLED: could not obtain access token', ['source_id' => $source->id]);

            return;
        }

        $this->fetchEvents($source, $config, $accessToken);
    }

    private function getAccessToken(Source $source): ?string
    {
        $cacheKey = "acled:access_token:{$source->id}";
        $cached = Redis::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        // Try refresh token first
        $refreshKey = "acled:refresh_token:{$source->id}";
        $refreshToken = Redis::get($refreshKey);

        if ($refreshToken) {
            $token = $this->refreshAccessToken($source, $refreshToken);
            if ($token) {
                return $token;
            }
        }

        // Full password auth
        return $this->authenticateWithPassword($source);
    }

    private function refreshAccessToken(Source $source, string $refreshToken): ?string
    {
        try {
            $response = Http::timeout(15)->asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => 'acled',
            ]);

            if ($response->successful() && $response->json('access_token')) {
                $this->cacheTokens($source, $response->json());

                return $response->json('access_token');
            }
        } catch (\Throwable $e) {
            Log::warning('ACLED: refresh token failed, falling back to password auth', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function authenticateWithPassword(Source $source): ?string
    {
        $config = $source->connector_config ?? [];
        $email = $config['email'] ?? config('services.acled.email');
        $password = $config['password'] ?? config('services.acled.password');

        if (empty($email) || empty($password)) {
            Log::warning('ACLED: email or password not configured', ['source_id' => $source->id]);

            return null;
        }

        try {
            $response = Http::timeout(15)->asForm()->post(self::TOKEN_URL, [
                'username' => $email,
                'password' => $password,
                'grant_type' => 'password',
                'client_id' => 'acled',
            ]);

            if ($response->successful() && $response->json('access_token')) {
                $this->cacheTokens($source, $response->json());

                return $response->json('access_token');
            }

            Log::warning('ACLED: authentication failed', [
                'source_id' => $source->id,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ACLED: authentication error', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function cacheTokens(Source $source, array $tokenData): void
    {
        if (! empty($tokenData['access_token'])) {
            Redis::setex("acled:access_token:{$source->id}", 82800, $tokenData['access_token']); // 23h
        }
        if (! empty($tokenData['refresh_token'])) {
            Redis::setex("acled:refresh_token:{$source->id}", 1123200, $tokenData['refresh_token']); // 13d
        }
    }

    private function fetchEvents(Source $source, array $config, string $accessToken): void
    {
        $limit = $config['limit'] ?? 5000;
        $daysBack = $config['days_back'] ?? 1;
        $maxPages = $config['max_pages'] ?? 10;
        $fromDate = now()->subDays($daysBack)->format('Y-m-d');

        $params = [
            'event_date' => $fromDate,
            'event_date_where' => '>=',
            'limit' => $limit,
            'fields' => self::FIELDS,
        ];

        if (! empty($config['country'])) {
            $params['country'] = $config['country'];
        }
        if (! empty($config['event_type'])) {
            $params['event_type'] = $config['event_type'];
        }

        try {
            $page = 1;
            $totalProcessed = 0;

            do {
                $params['page'] = $page;

                $response = Http::timeout(30)
                    ->withToken($accessToken)
                    ->get(self::API_BASE, $params);

                if ($response->failed()) {
                    Log::warning('ACLED API fetch failed', [
                        'source_id' => $source->id,
                        'status' => $response->status(),
                        'page' => $page,
                    ]);

                    return;
                }

                $data = $response->json('data', []);

                foreach ($data as $event) {
                    $this->processEvent($event, $source);
                }

                $totalProcessed += count($data);
                $page++;

                // Stop when we get fewer rows than the limit (last page)
            } while (count($data) >= $limit && $page <= $maxPages);

            Log::info('ACLED: fetched events', [
                'source_id' => $source->id,
                'events' => $totalProcessed,
                'pages' => $page - 1,
            ]);
        } catch (\Throwable $e) {
            Log::error('ACLED connector error', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processEvent(array $event, Source $source): void
    {
        $eventId = $event['event_id_cnty'] ?? null;
        $eventType = $event['event_type'] ?? '';
        $subEventType = $event['sub_event_type'] ?? '';
        $country = $event['country'] ?? '';
        $location = $event['location'] ?? '';
        $notes = $event['notes'] ?? '';
        $eventDate = $event['event_date'] ?? '';
        $fatalities = (int) ($event['fatalities'] ?? 0);
        $actor1 = $event['actor1'] ?? '';
        $actor2 = $event['actor2'] ?? '';

        // Dedup by ACLED event_id_cnty (stable unique identifier)
        $hash = md5("acled:{$eventId}:{$source->id}");

        if (Redis::get("event_hash:{$hash}")) {
            return;
        }

        Redis::setex("event_hash:{$hash}", 172800, '1'); // 48h TTL

        $title = trim("{$subEventType} in {$location}, {$country}") ?: "{$eventType} event";

        $rawContent = "{$eventType}: {$subEventType} in {$location}, {$country}.";
        if ($actor1) {
            $rawContent .= " Actor 1: {$actor1}.";
        }
        if ($actor2) {
            $rawContent .= " Actor 2: {$actor2}.";
        }
        if ($fatalities > 0) {
            $rawContent .= " Fatalities: {$fatalities}.";
        }
        if ($notes) {
            $rawContent .= " {$notes}";
        }

        $timestamp = $eventDate ? strtotime($eventDate) : time();

        ProcessRawEventJob::dispatch([
            'title' => $title,
            'raw_content' => $rawContent,
            'source_id' => $source->id,
            'hash' => $hash,
            'occurred_at' => date('Y-m-d H:i:s', $timestamp),
        ]);
    }
}
