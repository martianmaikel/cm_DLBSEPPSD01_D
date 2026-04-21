<?php

namespace App\Providers;

use App\Contracts\BriefingProvider;
use App\Contracts\ClassificationProvider;
use App\Contracts\EmbeddingProvider;
use App\Contracts\ThreatLevelProvider;
use App\Services\Briefing\LlmBriefingProvider;
use App\Services\Llm\ClaudeProvider;
use App\Services\Llm\GeminiProvider;
use App\Services\Llm\GrokProvider;
use App\Services\ThreatLevel\LlmThreatLevelProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ClassificationProvider::class, function () {
            return match (config('llm.default_classifier')) {
                'grok' => new GrokProvider(),
                'gemini' => new GeminiProvider(),
                'claude' => new ClaudeProvider(),
                default => throw new InvalidArgumentException(
                    'Unknown LLM classifier: ' . config('llm.default_classifier')
                ),
            };
        });

        $this->app->singleton(BriefingProvider::class, fn() => new LlmBriefingProvider());

        $this->app->singleton(ThreatLevelProvider::class, fn() => new LlmThreatLevelProvider());

        $this->app->singleton(EmbeddingProvider::class, function () {
            return match (config('llm.default_embedder')) {
                'grok' => new GrokProvider(),
                'gemini' => new GeminiProvider(),
                'claude' => new ClaudeProvider(),
                default => throw new InvalidArgumentException(
                    'Unknown LLM embedder: ' . config('llm.default_embedder')
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('nominatim', function () {
            return Limit::perSecond(1);
        });
    }
}
