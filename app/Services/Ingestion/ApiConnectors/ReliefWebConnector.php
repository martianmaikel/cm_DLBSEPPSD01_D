<?php

namespace App\Services\Ingestion\ApiConnectors;

use App\Contracts\SourceConnector;
use App\Jobs\ProcessRawEventJob;
use App\Models\Source;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * ReliefWeb API connector (UN OCHA).
 *
 * Humanitarian crisis reports filtered for conflict-related content.
 *
 * Requires pre-approved appname (since Nov 2025).
 * Docs: https://apidoc.reliefweb.int/
 * Quotas: max 1000 entries/call, max 1000 calls/day.
 *
 * connector_config options:
 *   - appname: pre-approved app identifier (default: "clashmonitor")
 *   - theme: filter theme (default: "Conflict and Violence")
 *   - limit: records per request, max 1000 (default: 50)
 *   - days_back: how many days to look back (default: 1)
 */
class ReliefWebConnector implements SourceConnector
{
    private const API_BASE = 'https://api.reliefweb.int/v2/reports';

    public function supports(Source $source): bool
    {
        return $source->connector_class === self::class;
    }

    public function poll(Source $source): void
    {
        $config = $source->connector_config ?? [];
        $appname = $config['appname'] ?? 'clashmonitor';
        $theme = $config['theme'] ?? 'Conflict and Violence';
        $limit = $config['limit'] ?? 50;
        $daysBack = $config['days_back'] ?? 1;

        $fromDate = now()->subDays($daysBack)->toIso8601String();

        $body = [
            'filter' => [
                'operator' => 'AND',
                'conditions' => [
                    ['field' => 'theme.name', 'value' => $theme],
                    ['field' => 'date.created', 'value' => ['from' => $fromDate]],
                ],
            ],
            'sort' => ['date:desc'],
            'limit' => $limit,
            'fields' => [
                'include' => ['title', 'date', 'country', 'source', 'url', 'body'],
            ],
        ];

        try {
            $response = Http::timeout(30)
                ->asJson()
                ->post(self::API_BASE . "?appname={$appname}", $body);

            if ($response->failed()) {
                Log::warning('ReliefWeb API fetch failed', [
                    'source_id' => $source->id,
                    'status' => $response->status(),
                ]);

                return;
            }

            $reports = $response->json('data', []);

            foreach ($reports as $report) {
                $this->processReport($report, $source);
            }

            Log::info('ReliefWeb: processed reports', [
                'source_id' => $source->id,
                'reports' => count($reports),
            ]);
        } catch (\Throwable $e) {
            Log::error('ReliefWeb connector error', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processReport(array $report, Source $source): void
    {
        $fields = $report['fields'] ?? [];
        $title = $fields['title'] ?? '';
        $url = $fields['url'] ?? '';

        if (empty($title)) {
            return;
        }

        // Dedup by report URL (unique per report)
        $hash = md5("reliefweb:{$url}:{$source->id}");

        if (Redis::get("event_hash:{$hash}")) {
            return;
        }

        Redis::setex("event_hash:{$hash}", 172800, '1'); // 48h TTL

        $country = '';
        if (! empty($fields['country']) && is_array($fields['country'])) {
            $countryNames = array_column($fields['country'], 'name');
            $country = implode(', ', $countryNames);
        }

        $sourceName = '';
        if (! empty($fields['source']) && is_array($fields['source'])) {
            $sourceNames = array_column($fields['source'], 'name');
            $sourceName = implode(', ', $sourceNames);
        }

        $dateCreated = $fields['date']['created'] ?? '';
        $body = $fields['body'] ?? '';

        // Truncate body for raw_content (keep first 1000 chars)
        $bodyExcerpt = mb_substr(strip_tags($body), 0, 1000);

        $rawContent = $title;
        if ($country) {
            $rawContent .= " [Country: {$country}]";
        }
        if ($sourceName) {
            $rawContent .= " [Source: {$sourceName}]";
        }
        if ($bodyExcerpt) {
            $rawContent .= " {$bodyExcerpt}";
        }

        $occurredAt = $dateCreated ? date('Y-m-d H:i:s', strtotime($dateCreated)) : now()->toDateTimeString();

        ProcessRawEventJob::dispatch([
            'title' => $title,
            'raw_content' => $rawContent,
            'source_id' => $source->id,
            'source_url' => $url ?: null,
            'hash' => $hash,
            'occurred_at' => $occurredAt,
        ]);
    }
}
