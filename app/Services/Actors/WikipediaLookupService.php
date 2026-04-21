<?php

namespace App\Services\Actors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikipediaLookupService
{
    private const USER_AGENT = 'ClashMonitor/1.0 (https://clashmonitor.com; contact@clashmonitor.com)';

    /**
     * Find a Wikipedia article for the given name. Falls back through:
     *   1. direct summary lookup (English)
     *   2. search then summary (English)
     *   3. direct summary (German)
     *   4. search then summary (German)
     *
     * Returns null if nothing usable was found.
     *
     * @param  array<int, string>  $aliases  Optional alias names to try on top of the canonical.
     * @return array{title: string, extract: string, description: ?string, url: string, thumbnail_url: ?string, language: string}|null
     */
    public function fetchByName(string $canonicalName, array $aliases = []): ?array
    {
        $candidates = array_values(array_unique(array_filter(array_map('trim', [$canonicalName, ...$aliases]))));

        foreach (['en', 'de'] as $lang) {
            foreach ($candidates as $name) {
                $summary = $this->fetchSummary($name, $lang);
                if ($summary && $this->isUsableSummary($summary)) {
                    return $summary;
                }

                $searchTitle = $this->searchTitle($name, $lang);
                if ($searchTitle && $searchTitle !== $name) {
                    $summary = $this->fetchSummary($searchTitle, $lang);
                    if ($summary && $this->isUsableSummary($summary)) {
                        return $summary;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array{title: string, extract: string, description: ?string, url: string, thumbnail_url: ?string, language: string, type: string}|null
     */
    private function fetchSummary(string $title, string $lang): ?array
    {
        $encoded = rawurlencode(str_replace(' ', '_', $title));
        $url = "https://{$lang}.wikipedia.org/api/rest_v1/page/summary/{$encoded}";

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(10)
                ->get($url);
        } catch (\Throwable $e) {
            Log::debug('Wikipedia fetchSummary failed', ['title' => $title, 'lang' => $lang, 'error' => $e->getMessage()]);
            return null;
        }

        if ($response->status() === 404 || $response->failed()) {
            return null;
        }

        $data = $response->json();

        $extract = trim((string) ($data['extract'] ?? ''));
        if ($extract === '') {
            return null;
        }

        return [
            'title' => (string) ($data['title'] ?? $title),
            'extract' => $extract,
            'description' => $data['description'] ?? null,
            'url' => $data['content_urls']['desktop']['page'] ?? "https://{$lang}.wikipedia.org/wiki/{$encoded}",
            'thumbnail_url' => $data['thumbnail']['source'] ?? null,
            'language' => $lang,
            'type' => (string) ($data['type'] ?? 'standard'),
        ];
    }

    private function isUsableSummary(array $summary): bool
    {
        if (($summary['type'] ?? 'standard') === 'disambiguation') {
            return false;
        }
        if (mb_strlen($summary['extract']) < 80) {
            return false;
        }
        return true;
    }

    private function searchTitle(string $query, string $lang): ?string
    {
        $url = "https://{$lang}.wikipedia.org/w/api.php";

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(10)
                ->get($url, [
                    'action' => 'opensearch',
                    'search' => $query,
                    'limit' => 1,
                    'namespace' => 0,
                    'format' => 'json',
                ]);
        } catch (\Throwable $e) {
            Log::debug('Wikipedia searchTitle failed', ['query' => $query, 'lang' => $lang, 'error' => $e->getMessage()]);
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $json = $response->json();
        // opensearch returns [query, [titles], [descriptions], [urls]]
        $titles = $json[1] ?? [];
        return isset($titles[0]) ? (string) $titles[0] : null;
    }
}
