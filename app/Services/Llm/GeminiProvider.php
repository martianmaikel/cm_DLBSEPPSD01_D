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
use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use RuntimeException;

class GeminiProvider implements ClassificationProvider, EmbeddingProvider
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private string $embeddingModel;
    private int $embeddingDimensions;

    public function __construct()
    {
        $this->apiKey = config('llm.providers.gemini.api_key');
        $this->baseUrl = rtrim(config('llm.providers.gemini.base_url'), '/');
        $this->model = config('llm.providers.gemini.model');
        $this->embeddingModel = config('llm.providers.gemini.embedding_model');
        $this->embeddingDimensions = config('llm.providers.gemini.embedding_dimensions');
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
            $response = Prism::structured()
                ->using(Provider::Gemini, $this->model)
                ->withSchema($this->classificationSchema())
                ->withPrompt($prompt)
                ->usingTemperature(0.1)
                ->withMaxTokens(2048)
                ->withProviderOptions(['thinkingBudget' => 0])
                ->withClientOptions(['timeout' => 60, 'connect_timeout' => 10])
                ->asStructured();

            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
            $tokensIn = $response->usage->inputTokens ?? AiUsageTracker::estimateTokens($prompt);
            $tokensOut = $response->usage->outputTokens ?? AiUsageTracker::estimateTokens(json_encode($response->structured));
            $tracker->log('gemini', $this->model, 'classify', $tokensIn, $tokensOut, $latencyMs);

            return ClassificationResult::fromArray($response->structured);
        } catch (PrismException $e) {
            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
            $tracker->log('gemini', $this->model, 'classify', AiUsageTracker::estimateTokens($prompt), 0, $latencyMs, 'error', $e->getMessage());
            Log::warning('Gemini classification failed via Prism', [
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Gemini classification failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function generateEmbedding(string $text): EmbeddingResult
    {
        $results = $this->generateBatchEmbeddings([$text]);

        return $results[0];
    }

    public function generateBatchEmbeddings(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $tracker = app(AiUsageTracker::class);
        $startTime = hrtime(true);

        try {
            $response = Prism::embeddings()
                ->using(Provider::Gemini, $this->embeddingModel)
                ->fromArray($texts)
                ->withClientOptions(['timeout' => 60, 'connect_timeout' => 10])
                ->asEmbeddings();

            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            $results = [];
            foreach ($response->embeddings as $embedding) {
                $results[] = new EmbeddingResult(
                    vector: $embedding->embedding,
                    dimensions: count($embedding->embedding),
                    provider: $this->getProviderName(),
                );
            }

            $tokensIn = $response->usage->inputTokens ?? AiUsageTracker::estimateTokens(implode('', $texts));
            $tracker->log('gemini', $this->embeddingModel, 'embed', $tokensIn, 0, $latencyMs, 'success', null, null, count($texts));

            return $results;
        } catch (PrismException|ConnectionException $e) {
            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
            $tracker->log('gemini', $this->embeddingModel, 'embed', AiUsageTracker::estimateTokens(implode('', $texts)), 0, $latencyMs, 'error', $e->getMessage(), null, count($texts));
            Log::error('Gemini batch embedding failed', ['error' => $e->getMessage(), 'count' => count($texts)]);
            throw new RuntimeException("Gemini batch embedding failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function getDimensions(): int
    {
        return $this->embeddingDimensions;
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    private function classificationSchema(): ObjectSchema
    {
        return new ObjectSchema(
            name: 'classification',
            description: 'Conflict event classification result',
            properties: [
                new BooleanSchema('relevant', 'Whether the report is relevant to armed conflict or security'),
                new EnumSchema('category', 'Event category', [
                    'war', 'terrorism', 'cyber', 'protest', 'disaster', 'diplomacy', 'economic',
                ]),
                new StringSchema('subcategory', 'Granular event type (e.g. airstrike, artillery, ground_offensive)'),
                new NumberSchema('severity', 'Impact scale 1-10', minimum: 1, maximum: 10),
                new ObjectSchema(
                    name: 'severity_factors',
                    description: 'Breakdown of severity dimensions',
                    properties: [
                        new NumberSchema('impact', 'Physical destruction and operational disruption 1-10', minimum: 1, maximum: 10),
                        new NumberSchema('casualty', 'Confirmed or likely human casualties 1-10', minimum: 1, maximum: 10),
                        new NumberSchema('escalation', 'Risk of widening conflict or retaliation 1-10', minimum: 1, maximum: 10),
                        new NumberSchema('international', 'Cross-border implications or foreign involvement 1-10', minimum: 1, maximum: 10),
                    ],
                    requiredFields: ['impact', 'casualty', 'escalation', 'international'],
                ),
                new NumberSchema('confidence', 'Certainty 1-10', minimum: 1, maximum: 10),
                new ArraySchema(
                    name: 'entities',
                    description: 'Named persons, organizations, and military units. Never include sovereign countries or generic roles.',
                    items: new ObjectSchema(
                        name: 'entity',
                        description: 'A named entity',
                        properties: [
                            new StringSchema('name', 'Name exactly as mentioned in the source'),
                            new EnumSchema('type', 'Entity type', ['person', 'organization', 'unit']),
                            new StringSchema('canonical_name', 'Most complete/official form of the name', nullable: true),
                            new StringSchema('role_context', 'Role/title for persons or brief function for organizations', nullable: true),
                        ],
                        requiredFields: ['name', 'type'],
                    ),
                ),
                new StringSchema('country', 'ISO 3166-1 alpha-2 country code', nullable: true),
                new StringSchema('region', 'Subnational region name', nullable: true),
                new NumberSchema('latitude', 'Latitude coordinate', nullable: true),
                new NumberSchema('longitude', 'Longitude coordinate', nullable: true),
                new StringSchema('title_en', 'Short English headline for the event (translate if source is non-English)'),
                new StringSchema('title_de', 'Short German headline for the event (always translate to German)'),
                new StringSchema('summary', 'Neutral 1-2 sentence summary of the event'),
                new StringSchema('summary_de', 'German translation of the summary (1-2 sentences, neutral tone)'),
                new StringSchema('conflict_context', 'Name of the broader armed conflict this event belongs to', nullable: true),
            ],
            requiredFields: [
                'relevant', 'category', 'severity', 'severity_factors',
                'confidence', 'entities', 'title_en', 'title_de', 'summary', 'summary_de',
            ],
        );
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
