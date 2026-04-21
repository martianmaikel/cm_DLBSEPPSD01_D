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

class GrokProvider implements ClassificationProvider, EmbeddingProvider
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private string $embeddingModel;
    private int $embeddingDimensions;

    public function __construct()
    {
        $this->apiKey = config('llm.providers.grok.api_key');
        $this->baseUrl = rtrim(config('llm.providers.grok.base_url'), '/');
        $this->model = config('llm.providers.grok.model');
        $this->embeddingModel = config('llm.providers.grok.embedding_model');
        $this->embeddingDimensions = config('llm.providers.grok.embedding_dimensions');
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
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                    'max_tokens' => 512,
                ]);

            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            if ($response->failed()) {
                $tracker->log('grok', $this->model, 'classify', AiUsageTracker::estimateTokens($prompt), 0, $latencyMs, 'error', "HTTP {$response->status()}");
                throw new RuntimeException("Grok API error: {$response->status()} {$response->body()}");
            }

            $tokensIn = $response->json('usage.prompt_tokens', 0) ?: AiUsageTracker::estimateTokens($prompt);
            $tokensOut = $response->json('usage.completion_tokens', 0);

            $content = $response->json('choices.0.message.content');

            if (empty($content)) {
                $tracker->log('grok', $this->model, 'classify', $tokensIn, $tokensOut, $latencyMs, 'error', 'Empty response');
                throw new RuntimeException('Grok returned empty classification response');
            }

            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $tracker->log('grok', $this->model, 'classify', $tokensIn, $tokensOut, $latencyMs, 'error', 'Invalid JSON');
                throw new RuntimeException('Grok returned invalid JSON: ' . json_last_error_msg());
            }

            if (! $tokensOut) {
                $tokensOut = AiUsageTracker::estimateTokens($content);
            }

            $tracker->log('grok', $this->model, 'classify', $tokensIn, $tokensOut, $latencyMs);

            return ClassificationResult::fromArray($data);
        } catch (ConnectionException $e) {
            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
            $tracker->log('grok', $this->model, 'classify', AiUsageTracker::estimateTokens($prompt), 0, $latencyMs, 'error', $e->getMessage());
            Log::error('Grok classify connection failed', ['error' => $e->getMessage()]);
            throw new RuntimeException("Grok connection failed: {$e->getMessage()}", 0, $e);
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
        $totalChars = array_sum(array_map('mb_strlen', $texts));

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->post("{$this->baseUrl}/embeddings", [
                    'model' => $this->embeddingModel,
                    'input' => $texts,
                ]);

            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            if ($response->failed()) {
                $tracker->log('grok', $this->embeddingModel, 'embed', AiUsageTracker::estimateTokens(implode('', $texts)), 0, $latencyMs, 'error', "HTTP {$response->status()}", null, count($texts));
                throw new RuntimeException("Grok embedding API error: {$response->status()} {$response->body()}");
            }

            $tokensIn = $response->json('usage.prompt_tokens', 0) ?: AiUsageTracker::estimateTokens(implode('', $texts));

            $dataItems = $response->json('data');

            if (empty($dataItems) || count($dataItems) !== count($texts)) {
                $tracker->log('grok', $this->embeddingModel, 'embed', $tokensIn, 0, $latencyMs, 'error', 'Unexpected embedding count', null, count($texts));
                throw new RuntimeException('Grok returned unexpected number of embeddings');
            }

            $results = [];
            foreach ($dataItems as $item) {
                $vector = $item['embedding'] ?? [];
                if (empty($vector)) {
                    throw new RuntimeException('Grok returned empty embedding in batch');
                }
                $results[] = new EmbeddingResult(
                    vector: $vector,
                    dimensions: count($vector),
                    provider: $this->getProviderName(),
                );
            }

            $tracker->log('grok', $this->embeddingModel, 'embed', $tokensIn, 0, $latencyMs, 'success', null, null, count($texts));

            return $results;
        } catch (ConnectionException $e) {
            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
            $tracker->log('grok', $this->embeddingModel, 'embed', AiUsageTracker::estimateTokens(implode('', $texts)), 0, $latencyMs, 'error', $e->getMessage(), null, count($texts));
            Log::error('Grok batch embedding failed', ['error' => $e->getMessage(), 'count' => count($texts)]);
            throw new RuntimeException("Grok batch embedding failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function getDimensions(): int
    {
        return $this->embeddingDimensions;
    }

    public function getProviderName(): string
    {
        return 'grok';
    }

    private function parseSourceContext(string $sourceContext): array
    {
        // sourceContext is expected as "name|type"
        $parts = explode('|', $sourceContext, 2);

        return [
            $parts[0] ?? 'Unknown',
            $parts[1] ?? 'rss',
        ];
    }
}
