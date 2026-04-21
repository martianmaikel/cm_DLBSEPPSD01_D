<?php

namespace App\Services\ThreatLevel;

use App\Contracts\ThreatLevelProvider;
use App\DataTransferObjects\ThreatLevelBilingualResult;
use App\Services\AiUsageTracker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use RuntimeException;

class LlmThreatLevelProvider implements ThreatLevelProvider
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
            throw new RuntimeException("LLM provider '{$this->provider}' is not configured for threat level generation.");
        }

        $this->apiKey = $config['api_key'];
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->model = $config['analysis_model'] ?? $config['model'];
    }

    public function generateBilingualSummary(array $context): ThreatLevelBilingualResult
    {
        $prompt = $this->buildPrompt($context);
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

            if (! $data || ! isset($data['summary_en'])) {
                $tracker->log($this->provider, $this->model, 'threat_level', AiUsageTracker::estimateTokens($prompt), 0, $latencyMs, 'error', 'Invalid response format');
                Log::warning('Threat level LLM returned invalid format', [
                    'content' => mb_substr(json_encode($data) ?? '', 0, 500),
                ]);
                throw new RuntimeException('Invalid threat level summary response format');
            }

            $outputJson = json_encode($data);
            $tracker->log(
                $this->provider, $this->model, 'threat_level',
                AiUsageTracker::estimateTokens($prompt),
                AiUsageTracker::estimateTokens($outputJson),
                $latencyMs,
            );

            $level = $context['level'] ?? 1;

            return new ThreatLevelBilingualResult(
                labelEn: $this->label($level, 'en'),
                labelDe: $this->label($level, 'de'),
                summaryEn: $data['summary_en'] ?? '',
                summaryDe: $data['summary_de'] ?? '',
            );
        } catch (RuntimeException $e) {
            if (! str_contains($e->getMessage(), 'Invalid threat level')) {
                $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
                $tracker->log($this->provider, $this->model, 'threat_level', AiUsageTracker::estimateTokens($prompt), 0, $latencyMs, 'error', $e->getMessage());
            }
            throw $e;
        }
    }

    private function label(int $level, string $locale): string
    {
        if ($locale === 'de') {
            return match (true) {
                $level >= 9 => 'KRITISCH',
                $level >= 7 => 'HOHE WARNSTUFE',
                $level >= 5 => 'ERHOEHT',
                $level >= 3 => 'NIEDRIG',
                default => 'MINIMAL',
            };
        }

        return match (true) {
            $level >= 9 => 'CRITICAL',
            $level >= 7 => 'HIGH ALERT',
            $level >= 5 => 'ELEVATED',
            $level >= 3 => 'LOW',
            default => 'MINIMAL',
        };
    }

    private function callGeminiStructured(string $prompt): array
    {
        $schema = new ObjectSchema(
            name: 'threat_level',
            description: 'Bilingual threat level summary',
            properties: [
                new StringSchema('summary_en', '2-3 sentences explaining the threat level in English'),
                new StringSchema('summary_de', '2-3 sentences explaining the threat level in German'),
            ],
            requiredFields: ['summary_en', 'summary_de'],
        );

        try {
            $response = Prism::structured()
                ->using(Provider::Gemini, $this->model)
                ->withSchema($schema)
                ->withPrompt($prompt)
                ->usingTemperature(0.3)
                ->withMaxTokens(4096)
                ->withProviderOptions(['thinkingBudget' => 0])
                ->withClientOptions(['timeout' => 60, 'connect_timeout' => 10])
                ->asStructured();

            return $response->structured;
        } catch (PrismException $e) {
            Log::warning('Gemini threat level generation failed via Prism', [
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Gemini threat level generation failed: ' . $e->getMessage(), 0, $e);
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
                'max_tokens' => 512,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("LLM API error: {$response->status()} {$response->body()}");
        }

        return $response->json('choices.0.message.content') ?? '';
    }

    private function buildPrompt(array $context): string
    {
        // Remove 'level' from context passed to LLM to prevent it from leaking the number into the text
        $contextForLlm = $context;
        unset($contextForLlm['level']);
        $contextJson = json_encode($contextForLlm, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
You are a conflict intelligence analyst for a real-time global monitoring platform called ClashMonitor.
Based on the following aggregated conflict data from the past 24 hours, explain the key factors driving the current World Threat Level. Provide the response in BOTH English and German.

RULES:
- Do NOT mention any specific threat level number or score anywhere in your response.
- Focus on which conflicts, regions, and trends are driving the assessment.
- Note escalation or de-escalation trends compared to the previous period.
- Use neutral, analytical language.

DATA:
{$contextJson}

Respond as a JSON object with this exact structure:
{
  "summary_en": "2-3 sentences in English explaining which conflicts and regions drive the current threat level. Note escalation or de-escalation trends.",
  "summary_de": "2-3 sentences in German explaining which conflicts and regions drive the current threat level. Note escalation or de-escalation trends."
}
PROMPT;
    }
}
