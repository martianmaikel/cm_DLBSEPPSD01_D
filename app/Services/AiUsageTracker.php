<?php

namespace App\Services;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Log;

class AiUsageTracker
{
    // Pricing per 1M tokens in USD (as of April 2026)
    private const PRICING = [
        'gemini' => [
            'gemini-3-flash-preview'        => ['input' => 0.50, 'output' => 3.00],
            'gemini-2.1-flash-lite-preview' => ['input' => 0.25, 'output' => 1.50],
            'gemini-embedding-001'          => ['input' => 0.00, 'output' => 0.00], // free tier
        ],
        'grok' => [
            'grok-3'         => ['input' => 3.00, 'output' => 15.00],
            'grok-embedding' => ['input' => 0.00, 'output' => 0.00],
        ],
        'claude' => [
            'claude-sonnet-4-6' => ['input' => 3.00, 'output' => 15.00],
        ],
    ];

    // Rough chars-per-token ratios for estimation when API doesn't return token counts
    private const CHARS_PER_TOKEN = 4;

    public function log(
        string $provider,
        string $model,
        string $operation,
        int $tokensInput = 0,
        int $tokensOutput = 0,
        int $latencyMs = 0,
        string $status = 'success',
        ?string $errorMessage = null,
        ?string $eventId = null,
        int $batchSize = 1,
    ): void {
        try {
            $cost = $this->estimateCost($provider, $model, $tokensInput, $tokensOutput);

            AiUsageLog::create([
                'provider' => $provider,
                'model' => $model,
                'operation' => $operation,
                'tokens_input' => $tokensInput,
                'tokens_output' => $tokensOutput,
                'estimated_cost' => $cost,
                'latency_ms' => $latencyMs,
                'status' => $status,
                'error_message' => $errorMessage ? mb_substr($errorMessage, 0, 500) : null,
                'event_id' => $eventId,
                'batch_size' => $batchSize,
            ]);
        } catch (\Throwable $e) {
            // Never let usage tracking break the actual pipeline
            Log::warning('Failed to log AI usage', ['error' => $e->getMessage()]);
        }
    }

    public static function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / self::CHARS_PER_TOKEN));
    }

    private function estimateCost(string $provider, string $model, int $tokensInput, int $tokensOutput): float
    {
        $pricing = self::PRICING[$provider][$model] ?? null;

        if (! $pricing) {
            return 0.0;
        }

        $inputCost = ($tokensInput / 1_000_000) * $pricing['input'];
        $outputCost = ($tokensOutput / 1_000_000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }
}
