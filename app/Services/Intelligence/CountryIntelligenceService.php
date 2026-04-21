<?php

namespace App\Services\Intelligence;

use App\Models\CountryIntelligence;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CountryIntelligenceService
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $provider = config('llm.default_classifier');
        $config = config("llm.providers.{$provider}");

        $this->apiKey = $config['api_key'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? '', '/');
        $this->model = $config['model'] ?? '';
    }

    public function refreshAll(): int
    {
        // Find all countries with events in the last 7 days
        $countries = Event::where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('country')
            ->whereNotIn('status', ['pending_classification', 'retracted'])
            ->select('country')
            ->selectRaw('COUNT(*) as event_count')
            ->groupBy('country')
            ->orderByDesc('event_count')
            ->limit(30)
            ->pluck('event_count', 'country');

        $refreshed = 0;
        foreach ($countries as $code => $count) {
            try {
                $this->refreshCountry($code, $count >= 3);
                $refreshed++;
            } catch (\Throwable $e) {
                Log::error('Country intelligence refresh failed', [
                    'country' => $code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $refreshed;
    }

    public function refreshCountry(string $countryCode, bool $generateBriefing = true): void
    {
        $countryCode = strtoupper($countryCode);
        $countryNames = config('geo.country_names', []);
        $countryName = $countryNames[$countryCode] ?? $countryCode;
        $continentSlug = config('geo.country_to_continent')[$countryCode] ?? null;

        // Compute stats
        $stats = Event::where('country', $countryCode)
            ->whereNotIn('status', ['pending_classification', 'retracted'])
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as last_24h,
                MAX(severity) as max_sev,
                AVG(severity) as avg_sev
            ", [now()->subHours(24)])
            ->first();

        // Category breakdown
        $categories = Event::where('country', $countryCode)
            ->whereNotIn('status', ['pending_classification', 'retracted'])
            ->where('created_at', '>=', now()->subDays(7))
            ->select('category')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        // Active threads for this country
        $threadIds = Event::where('country', $countryCode)
            ->whereNotNull('conflict_thread_id')
            ->where('created_at', '>=', now()->subDays(7))
            ->distinct()
            ->pluck('conflict_thread_id')
            ->toArray();

        // Compute threat level: weighted formula
        $threatLevel = $this->computeThreatLevel(
            eventCount24h: $stats->last_24h ?? 0,
            maxSeverity: $stats->max_sev ?? 0,
            avgSeverity: $stats->avg_sev ?? 0,
        );

        $data = [
            'country_code' => $countryCode,
            'country_name' => $countryName,
            'continent_slug' => $continentSlug,
            'threat_level' => $threatLevel,
            'event_count_24h' => $stats->last_24h ?? 0,
            'event_count_total' => $stats->total ?? 0,
            'max_severity' => $stats->max_sev ?? 0,
            'avg_severity' => round($stats->avg_sev ?? 0, 1),
            'category_breakdown' => $categories,
            'active_thread_ids' => $threadIds,
            'generated_at' => now(),
        ];

        // Generate LLM briefing for countries with enough events
        if ($generateBriefing && $this->apiKey) {
            try {
                $briefings = $this->generateCountryBriefing($countryCode, $countryName);
                $data['intelligence_briefing_en'] = $briefings['en'];
                $data['intelligence_briefing_de'] = $briefings['de'];
            } catch (\Throwable $e) {
                Log::warning('Country briefing generation failed', [
                    'country' => $countryCode,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        CountryIntelligence::updateOrCreate(
            ['country_code' => $countryCode],
            $data,
        );
    }

    private function computeThreatLevel(int $eventCount24h, int $maxSeverity, float $avgSeverity): int
    {
        // Weighted formula: 40% max severity, 30% avg severity, 30% activity volume
        $volumeScore = min(10, $eventCount24h * 0.5);
        $score = ($maxSeverity * 0.4) + ($avgSeverity * 0.3) + ($volumeScore * 0.3);

        return max(1, min(10, (int) round($score)));
    }

    private function generateCountryBriefing(string $countryCode, string $countryName): array
    {
        // Get recent events for this country
        $recentEvents = Event::where('country', $countryCode)
            ->whereNotIn('status', ['pending_classification', 'retracted'])
            ->where('created_at', '>=', now()->subHours(48))
            ->orderByDesc('severity')
            ->limit(15)
            ->get(['title', 'summary', 'category', 'severity', 'occurred_at']);

        if ($recentEvents->isEmpty()) {
            return ['en' => null, 'de' => null];
        }

        $eventsJson = $recentEvents->map(fn ($e) => [
            'title' => $e->title,
            'summary' => $e->summary,
            'category' => $e->category,
            'severity' => $e->severity,
        ])->toJson(JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
You are a conflict intelligence analyst. Generate a concise intelligence briefing for {$countryName} based on the following recent events. Provide the briefing in BOTH English and German.

RECENT EVENTS:
{$eventsJson}

Respond as a JSON object:
{
  "briefing_en": "2-3 paragraph intelligence briefing analyzing the current situation, key threats, and outlook for {$countryName}. Neutral, factual tone. Focus on patterns, escalation risks, and strategic implications.",
  "briefing_de": "Same briefing in German."
}

Do not editorialize. Focus on analysis, not event repetition.
PROMPT;

        $response = Http::withToken($this->apiKey)
            ->timeout(45)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
                'max_tokens' => 2048,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Briefing API call failed: HTTP {$response->status()}");
        }

        $content = $response->json('choices.0.message.content');
        $data = json_decode($content, true);

        return [
            'en' => $data['briefing_en'] ?? null,
            'de' => $data['briefing_de'] ?? null,
        ];
    }
}
