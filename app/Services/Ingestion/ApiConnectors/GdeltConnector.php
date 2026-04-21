<?php

namespace App\Services\Ingestion\ApiConnectors;

use App\Contracts\SourceConnector;
use App\Jobs\ProcessRawEventJob;
use App\Models\Source;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * GDELT DOC 2.0 API connector — global conflict article search.
 *
 * Searches GDELT's full-text index of worldwide news coverage (65 languages,
 * rolling 3-month window) for conflict-relevant articles using theme operators.
 *
 * Free, no API key required.
 * Docs: https://blog.gdeltproject.org/gdelt-doc-2-0-api-debuts/
 *
 * connector_config options:
 *   - query: GDELT query with operators (default: conflict themes)
 *   - timespan: search window (default: "15min")
 *   - max_records: results per poll, max 250 (default: 250)
 *   - sort: result sorting (default: "datedesc")
 */
class GdeltConnector implements SourceConnector
{
    use GdeltRateLimitTrait;

    private const API_BASE = 'https://api.gdeltproject.org/api/v2/doc/doc';

    private const DEFAULT_QUERY = '(theme:MILITARY OR theme:TERROR OR theme:KILL OR theme:ARMED_CONFLICT OR theme:TAX_WEAPONS) tone<-3';

    public function supports(Source $source): bool
    {
        return $source->connector_class === self::class;
    }

    public function poll(Source $source): void
    {
        if ($this->isBackedOff($source->id)) {
            return;
        }

        $config = $source->connector_config ?? [];
        $query = $config['query'] ?? self::DEFAULT_QUERY;
        $timespan = $config['timespan'] ?? '3h';
        $maxRecords = $config['max_records'] ?? 250;
        $sort = $config['sort'] ?? 'datedesc';

        try {
            $response = Http::connectTimeout(15)->timeout(60)->get(self::API_BASE, [
                'query' => $query,
                'mode' => 'artlist',
                'format' => 'json',
                'timespan' => $timespan,
                'maxrecords' => $maxRecords,
                'sort' => $sort,
            ]);

            if ($response->status() === 429) {
                $this->applyBackoff($source->id);
                Log::warning('GDELT DOC API rate-limited, backing off', [
                    'source_id' => $source->id,
                ]);

                return;
            }

            if ($response->failed()) {
                Log::warning('GDELT DOC API fetch failed', [
                    'source_id' => $source->id,
                    'status' => $response->status(),
                ]);

                return;
            }

            // Success — clear any backoff
            $this->clearBackoff($source->id);

            $articles = $response->json('articles', []);

            foreach ($articles as $article) {
                $this->processArticle($article, $source);
            }

            Log::info('GDELT DOC: processed articles', [
                'source_id' => $source->id,
                'articles' => count($articles),
            ]);
        } catch (\Throwable $e) {
            Log::error('GDELT DOC connector error', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processArticle(array $article, Source $source): void
    {
        $title = $article['title'] ?? '';
        $url = $article['url'] ?? '';
        $seenDate = $article['seendate'] ?? '';

        if (empty($title)) {
            return;
        }

        // Dedup by URL (stable unique identifier per article)
        $hash = md5("gdelt:{$url}:{$source->id}");

        if (Redis::get("event_hash:{$hash}")) {
            return;
        }

        Redis::setex("event_hash:{$hash}", 172800, '1'); // 48h TTL

        $rawContent = $title;
        if (! empty($article['sourcecountry'])) {
            $rawContent .= " [Country: {$article['sourcecountry']}]";
        }
        if (! empty($article['language'])) {
            $rawContent .= " [Language: {$article['language']}]";
        }
        if (! empty($article['domain'])) {
            $rawContent .= " [Source: {$article['domain']}]";
        }
        if (! empty($url)) {
            $rawContent .= " [URL: {$url}]";
        }

        $timestamp = $seenDate ? strtotime($seenDate) : time();

        ProcessRawEventJob::dispatch([
            'title' => $title,
            'raw_content' => $rawContent,
            'source_id' => $source->id,
            'source_url' => $url ?: null,
            'hash' => $hash,
            'occurred_at' => date('Y-m-d H:i:s', $timestamp),
        ]);
    }
}
