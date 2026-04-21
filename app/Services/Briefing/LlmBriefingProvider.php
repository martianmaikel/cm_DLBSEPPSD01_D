<?php

namespace App\Services\Briefing;

use App\Contracts\BriefingProvider;
use App\DataTransferObjects\BilingualBriefingResult;
use App\Services\AiUsageTracker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use RuntimeException;

class LlmBriefingProvider implements BriefingProvider
{
    private string $provider;
    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->provider = config('llm.default_classifier');
        $config = config("llm.providers.{$this->provider}");

        if (! $config || empty($config['api_key'])) {
            throw new RuntimeException("LLM provider '{$this->provider}' is not configured for briefing generation.");
        }

        $this->apiKey = $config['api_key'];
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->model = $config['analysis_model'] ?? $config['model'];
    }

    public function generateBilingualBriefing(array $eventSummaries, array $threadSummaries, array $comparisonContext = []): BilingualBriefingResult
    {
        $prompt = $this->buildPrompt($eventSummaries, $threadSummaries, $comparisonContext);
        $tracker = app(AiUsageTracker::class);
        $startTime = hrtime(true);

        try {
            if ($this->provider === 'gemini') {
                $data = $this->callGeminiStructured($prompt);
            } else {
                $content = $this->callOpenAiCompatible($prompt);
                $data = json_decode($content, true);
            }

            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            if (! $data || ! isset($data['title_en'])) {
                $tracker->log($this->provider, $this->model, 'briefing', AiUsageTracker::estimateTokens($prompt), 0, $latencyMs, 'error', 'Invalid response format');
                throw new RuntimeException('Invalid briefing response format');
            }

            $outputJson = json_encode($data);
            $tracker->log(
                $this->provider, $this->model, 'briefing',
                AiUsageTracker::estimateTokens($prompt),
                AiUsageTracker::estimateTokens($outputJson),
                $latencyMs,
            );

            return new BilingualBriefingResult(
                titleEn: $data['title_en'] ?? 'Daily Intelligence Briefing',
                titleDe: $data['title_de'] ?? 'Taegliches Lagebriefing',
                summaryEn: $data['summary_en'] ?? '',
                summaryDe: $data['summary_de'] ?? '',
                keyDevelopmentsEn: $data['key_developments_en'] ?? [],
                keyDevelopmentsDe: $data['key_developments_de'] ?? [],
                conflictSectionsEn: $data['conflict_sections_en'] ?? [],
                conflictSectionsDe: $data['conflict_sections_de'] ?? [],
                statistics: $data['statistics'] ?? [],
            );
        } catch (RuntimeException $e) {
            if (! str_contains($e->getMessage(), 'Invalid briefing')) {
                $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
                $tracker->log($this->provider, $this->model, 'briefing', AiUsageTracker::estimateTokens($prompt), 0, $latencyMs, 'error', $e->getMessage());
            }
            throw $e;
        }
    }

    private function callGeminiStructured(string $prompt): array
    {
        $developmentSchema = new ObjectSchema(
            name: 'development',
            description: 'A key development',
            properties: [
                new StringSchema('title', 'Development headline'),
                new StringSchema('description', 'Brief description'),
                new NumberSchema('severity', 'Severity 1-10', minimum: 1, maximum: 10),
            ],
            requiredFields: ['title', 'description', 'severity'],
        );

        $conflictSectionSchema = new ObjectSchema(
            name: 'conflict_section',
            description: 'Per-conflict summary section',
            properties: [
                new StringSchema('conflict_name', 'Name of the conflict thread'),
                new NumberSchema('event_count', 'Number of events in last 24h'),
                new StringSchema('summary', '2-3 sentence summary of recent developments in this conflict'),
                new NumberSchema('max_severity', 'Highest severity event in this conflict (1-10)', minimum: 1, maximum: 10),
            ],
            requiredFields: ['conflict_name', 'event_count', 'summary', 'max_severity'],
        );

        $schema = new ObjectSchema(
            name: 'briefing',
            description: 'Bilingual daily intelligence briefing',
            properties: [
                new StringSchema('title_en', 'Briefing headline in English'),
                new StringSchema('title_de', 'Briefing headline in German'),
                new StringSchema('summary_en', 'Global overview in 3-4 sentences in English'),
                new StringSchema('summary_de', 'Global overview in 3-4 sentences in German'),
                new ArraySchema('key_developments_en', 'Key developments in English', $developmentSchema),
                new ArraySchema('key_developments_de', 'Key developments in German', $developmentSchema),
                new ArraySchema('conflict_sections_en', 'Per-conflict breakdowns in English', $conflictSectionSchema),
                new ArraySchema('conflict_sections_de', 'Per-conflict breakdowns in German', $conflictSectionSchema),
                new ObjectSchema(
                    name: 'statistics',
                    description: 'Aggregate statistics',
                    properties: [
                        new NumberSchema('total_events', 'Total number of events'),
                        new NumberSchema('avg_severity', 'Average severity'),
                        new ArraySchema('top_categories', 'Top event categories', new StringSchema('category', 'Category name')),
                        new ArraySchema('top_countries', 'Top affected countries', new StringSchema('country', 'Country name')),
                        new NumberSchema('new_threads', 'Number of new conflict threads'),
                    ],
                    requiredFields: ['total_events', 'avg_severity', 'top_categories', 'top_countries', 'new_threads'],
                ),
            ],
            requiredFields: ['title_en', 'title_de', 'summary_en', 'summary_de', 'key_developments_en', 'key_developments_de', 'conflict_sections_en', 'conflict_sections_de', 'statistics'],
        );

        try {
            $response = Prism::structured()
                ->using(Provider::Gemini, $this->model)
                ->withSchema($schema)
                ->withPrompt($prompt)
                ->usingTemperature(0.3)
                ->withMaxTokens(16384)
                ->withProviderOptions(['thinkingBudget' => 0])
                ->withClientOptions(['timeout' => 90, 'connect_timeout' => 10])
                ->asStructured();

            return $response->structured;
        } catch (PrismException $e) {
            Log::error('Gemini briefing generation failed via Prism', [
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Gemini briefing generation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function callOpenAiCompatible(string $prompt): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
                'max_tokens' => 4096,
            ]);

        if (! $response->successful()) {
            Log::error('Briefing generation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException("Briefing generation failed: HTTP {$response->status()}");
        }

        return $response->json('choices.0.message.content') ?? '';
    }

    private function buildPrompt(array $eventSummaries, array $threadSummaries, array $comparisonContext = []): string
    {
        $eventsJson = json_encode($eventSummaries, JSON_UNESCAPED_UNICODE);
        $threadsJson = json_encode($threadSummaries, JSON_UNESCAPED_UNICODE);

        $comparisonBlock = '';
        if (! empty($comparisonContext) && ($comparisonContext['has_previous_data'] ?? false)) {
            // Exclude previous summaries from the metrics JSON — they are added as a separate block below
            $metricsOnly = array_diff_key($comparisonContext, array_flip(['previous_summary_en', 'previous_summary_de']));
            $comparisonJson = json_encode($metricsOnly, JSON_UNESCAPED_UNICODE);
            $comparisonBlock = <<<COMPARISON


DAY-OVER-DAY COMPARISON DATA (today vs. {$comparisonContext['previous_date']}):
{$comparisonJson}

Key fields explained:
- severity_trend: "rising" = avg severity increased, "falling" = decreased, "stable" = roughly unchanged
- thread_trends: per-conflict trend — "escalating", "de-escalating", "stable", or "new" (first appearance)
- new_hotspots: countries with events today that had NO events yesterday
- cooling_hotspots: countries that had events yesterday but NONE today
- category_shifts: event types that significantly increased or decreased
- previous_summary_en/de: yesterday's briefing summary for narrative continuity

COMPARISON;

            if (! empty($comparisonContext['previous_summary_en'])) {
                $comparisonBlock .= <<<PREV

YESTERDAY'S BRIEFING SUMMARY (for reference — do NOT repeat it, use it to provide continuity):
{$comparisonContext['previous_summary_en']}

PREV;
            }
        }

        return <<<PROMPT
You are a conflict intelligence analyst. Generate a structured daily briefing summarizing the following events and conflict threads from the past 24 hours. Provide all text content in BOTH English and German.

EVENTS (with their assigned conflict thread):
{$eventsJson}

ACTIVE CONFLICT THREADS (with 24h event counts):
{$threadsJson}{$comparisonBlock}

Respond as a JSON object with this exact structure:
{
  "title_en": "Brief headline for the daily briefing (in English)",
  "title_de": "Brief headline for the daily briefing (in German)",
  "summary_en": "A concise global overview in exactly 3-4 sentences covering the most important developments across all conflicts (in English)",
  "summary_de": "A concise global overview in exactly 3-4 sentences covering the most important developments across all conflicts (in German)",
  "key_developments_en": [
    {"title": "Development headline in English", "description": "Brief description in English", "severity": 1-10}
  ],
  "key_developments_de": [
    {"title": "Development headline in German", "description": "Brief description in German", "severity": 1-10}
  ],
  "conflict_sections_en": [
    {"conflict_name": "Name of the conflict", "event_count": <number>, "summary": "2-3 sentence summary of what happened in this conflict in the last 24h", "max_severity": 1-10}
  ],
  "conflict_sections_de": [
    {"conflict_name": "Name des Konflikts", "event_count": <number>, "summary": "2-3 Saetze Zusammenfassung der Entwicklungen in diesem Konflikt in den letzten 24h", "max_severity": 1-10}
  ],
  "statistics": {
    "total_events": <number>,
    "avg_severity": <number>,
    "top_categories": ["category1", "category2"],
    "top_countries": ["country1", "country2"],
    "new_threads": <number>
  }
}

IMPORTANT RULES:
- The global summary (summary_en/de) must be exactly 3-4 sentences — a high-level overview, not a list of events.
- conflict_sections: Include ONLY conflicts that had a meaningful number of events (5+) in the last 24h. Sort by severity/significance descending.
- Each conflict section summary should describe what specifically happened — troop movements, strikes, diplomatic shifts — not just restate that events occurred.
- Limit key_developments to the 5 most significant individual events.
- Focus on accuracy and neutrality. Do not editorialize.

COMPARATIVE ANALYSIS RULES (apply ONLY when day-over-day comparison data is provided above):
- You MUST reference trends and changes compared to the previous day. Do NOT write the briefing as if every day is a fresh escalation.
- Use the severity_trend and thread_trends data to frame the narrative: "Compared to the previous day, the situation in X has escalated/de-escalated/remained stable..."
- If new_hotspots exist, explicitly mention them as newly emerging areas of concern.
- If cooling_hotspots exist, note where activity has subsided.
- If the overall event count or severity is similar to the previous day, say so — avoid false urgency.
- Reference yesterday's briefing for continuity: follow up on developments mentioned there, note what has changed and what persists.
- The title should reflect the actual change: "Escalation in X, Calm in Y" is better than generic "Global Tensions Rise" when the data shows a mixed picture.
- Be specific about what changed: "Artillery strikes in Kherson increased by 40% compared to the previous day" is better than "intense shelling continues".
PROMPT;
    }
}
