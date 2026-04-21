<?php

namespace App\Services\Llm;

use App\Contracts\ClassificationProvider;
use App\Contracts\EmbeddingProvider;
use App\DataTransferObjects\ClassificationResult;
use App\DataTransferObjects\EmbeddingResult;
use App\Services\AiUsageTracker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ClaudeProvider implements ClassificationProvider, EmbeddingProvider
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private string $embeddingModel;
    private int $embeddingDimensions;

    private const ANTHROPIC_VERSION = '2023-06-01';

    private const CLASSIFICATION_TOOL = [
        'name' => 'classify_event',
        'description' => 'Extract structured classification data from a conflict event report.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'category' => [
                    'type' => 'string',
                    'enum' => ['war', 'terrorism', 'cyber', 'protest', 'disaster', 'diplomacy', 'economic'],
                ],
                'subcategory' => [
                    'type' => ['string', 'null'],
                    'description' => 'Granular event type (e.g. airstrike, artillery, ground_offensive, cyber_espionage, ransomware, etc.)',
                ],
                'severity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                'severity_factors' => [
                    'type' => 'object',
                    'properties' => [
                        'impact' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                        'casualty' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                        'escalation' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                        'international' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                    ],
                    'required' => ['impact', 'casualty', 'escalation', 'international'],
                ],
                'confidence' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                'entities' => [
                    'type' => 'array',
                    'description' => 'Named persons, organizations, and military units. Never include sovereign countries or generic roles.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Name exactly as mentioned in the source'],
                            'type' => ['type' => 'string', 'enum' => ['person', 'organization', 'unit']],
                            'canonical_name' => ['type' => ['string', 'null'], 'description' => 'Most complete/official form of the name if known'],
                            'role_context' => ['type' => ['string', 'null'], 'description' => 'For persons: role/title as stated in source. For organizations: brief function. Null if not inferrable.'],
                        ],
                        'required' => ['name', 'type'],
                    ],
                ],
                'country' => ['type' => ['string', 'null']],
                'region' => ['type' => ['string', 'null']],
                'latitude' => ['type' => ['number', 'null']],
                'longitude' => ['type' => ['number', 'null']],
                'title_en' => ['type' => ['string', 'null'], 'description' => 'Short English headline for the event (translate if non-English source)'],
                'title_de' => ['type' => ['string', 'null'], 'description' => 'Short German headline for the event (always translate to German)'],
                'summary' => ['type' => 'string'],
                'summary_de' => ['type' => ['string', 'null'], 'description' => 'German translation of the summary (1-2 sentences, neutral tone)'],
                'conflict_context' => ['type' => ['string', 'null'], 'description' => 'Name of the broader armed conflict this event belongs to'],
            ],
            'required' => ['category', 'severity', 'severity_factors', 'confidence', 'entities', 'summary'],
        ],
    ];

    public function __construct()
    {
        $this->apiKey = config('llm.providers.claude.api_key');
        $this->baseUrl = rtrim(config('llm.providers.claude.base_url'), '/');
        $this->model = config('llm.providers.claude.model');
        $this->embeddingModel = config('llm.providers.claude.embedding_model');
        $this->embeddingDimensions = config('llm.providers.claude.embedding_dimensions');
    }

    public function classify(string $rawContent, string $sourceContext): ClassificationResult
    {
        [$sourceName, $sourceType] = $this->parseSourceContext($sourceContext);

        $prompt = str_replace(
            ['{source_name}', '{source_type}', '{raw_content}', '{current_date}'],
            [$sourceName, $sourceType, $rawContent, now()->toDateString()],
            config('llm.classification_prompt')
        );

        $tracker = app(AiUsageTracker::class);
        $startTime = hrtime(true);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type' => 'application/json',
            ])
                ->timeout(30)
                ->post("{$this->baseUrl}/messages", [
                    'model' => $this->model,
                    'max_tokens' => 1024,
                    'tools' => [self::CLASSIFICATION_TOOL],
                    'tool_choice' => ['type' => 'tool', 'name' => 'classify_event'],
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            if ($response->failed()) {
                $tracker->log('claude', $this->model, 'classify', AiUsageTracker::estimateTokens($prompt), 0, $latencyMs, 'error', "HTTP {$response->status()}");
                throw new RuntimeException("Claude API error: {$response->status()} {$response->body()}");
            }

            $tokensIn = $response->json('usage.input_tokens', 0);
            $tokensOut = $response->json('usage.output_tokens', 0);

            $toolUseBlock = collect($response->json('content'))
                ->firstWhere('type', 'tool_use');

            if (empty($toolUseBlock)) {
                $tracker->log('claude', $this->model, 'classify', $tokensIn, $tokensOut, $latencyMs, 'error', 'No tool_use block');
                throw new RuntimeException('Claude returned no tool_use block in response');
            }

            $data = $toolUseBlock['input'] ?? [];

            if (empty($data)) {
                $tracker->log('claude', $this->model, 'classify', $tokensIn, $tokensOut, $latencyMs, 'error', 'Empty tool input');
                throw new RuntimeException('Claude tool_use block has empty input');
            }

            $tracker->log('claude', $this->model, 'classify', $tokensIn, $tokensOut, $latencyMs);

            return ClassificationResult::fromArray($data);
        } catch (ConnectionException $e) {
            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
            $tracker->log('claude', $this->model, 'classify', AiUsageTracker::estimateTokens($prompt), 0, $latencyMs, 'error', $e->getMessage());
            Log::error('Claude classify connection failed', ['error' => $e->getMessage()]);
            throw new RuntimeException("Claude connection failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function generateEmbedding(string $text): EmbeddingResult
    {
        throw new RuntimeException(
            'ClaudeProvider does not support embeddings. Configure a different LLM_EMBEDDER (grok or gemini).'
        );
    }

    public function generateBatchEmbeddings(array $texts): array
    {
        throw new RuntimeException(
            'ClaudeProvider does not support embeddings. Configure a different LLM_EMBEDDER (grok or gemini).'
        );
    }

    public function getDimensions(): int
    {
        return $this->embeddingDimensions;
    }

    public function getProviderName(): string
    {
        return 'claude';
    }

    private function parseSourceContext(string $sourceContext): array
    {
        $parts = explode('|', $sourceContext, 2);

        return [
            $parts[0] ?? 'Unknown',
            $parts[1] ?? 'rss',
        ];
    }
}
