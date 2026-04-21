<?php

namespace App\Services\Threading;

use App\Models\ConflictThread;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConflictLifecycleService
{
    private const INACTIVE_DAYS = 14;
    private const MERGE_SIMILARITY_THRESHOLD = 0.6;
    private const PROMOTION_EVENT_THRESHOLD = 10;
    private const PROMOTION_HOURS = 48;

    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $provider = config('llm.default_classifier');
        $providerConfig = config("llm.providers.{$provider}");

        $this->apiKey = $providerConfig['api_key'] ?? '';
        $this->baseUrl = rtrim($providerConfig['base_url'] ?? '', '/');
        $this->model = $providerConfig['model'] ?? '';
    }

    /**
     * Run all lifecycle operations in sequence.
     */
    public function run(): array
    {
        $results = [
            'closed' => $this->closeInactiveThreads(),
            'merged' => $this->mergeDuplicateThreads(),
            'promoted' => $this->promoteEmergingConflicts(),
            'summaries_updated' => $this->refreshStaleSummaries(),
        ];

        Log::info('Conflict lifecycle run completed', $results);

        return $results;
    }

    /**
     * Close threads with no events in the last N days.
     */
    public function closeInactiveThreads(): int
    {
        $cutoff = now()->subDays(self::INACTIVE_DAYS);

        // Find open threads whose most recent event is older than the cutoff
        $inactiveThreads = ConflictThread::open()
            ->whereDoesntHave('events', function ($q) use ($cutoff) {
                $q->where('occurred_at', '>=', $cutoff);
            })
            ->whereHas('events') // only close threads that had events at some point
            ->get();

        $closed = 0;
        foreach ($inactiveThreads as $thread) {
            $thread->update(['status' => 'closed']);

            Log::info('Conflict thread auto-closed (inactive)', [
                'thread_id' => $thread->id,
                'thread_name' => $thread->name,
            ]);

            $closed++;
        }

        return $closed;
    }

    /**
     * Detect and merge duplicate threads using fuzzy name matching.
     *
     * Keeps the thread with more events as the canonical one and re-assigns
     * events from the duplicate.
     */
    public function mergeDuplicateThreads(): int
    {
        $openThreads = ConflictThread::open()
            ->withCount('events')
            ->orderByDesc('events_count')
            ->get();

        $merged = 0;
        $consumed = []; // IDs of threads already merged into another

        for ($i = 0; $i < $openThreads->count(); $i++) {
            $primary = $openThreads[$i];

            if (in_array($primary->id, $consumed)) {
                continue;
            }

            $primaryTokens = $this->normalizeTokens($primary->name);
            if (empty($primaryTokens)) {
                continue;
            }

            for ($j = $i + 1; $j < $openThreads->count(); $j++) {
                $candidate = $openThreads[$j];

                if (in_array($candidate->id, $consumed)) {
                    continue;
                }

                $candidateTokens = $this->normalizeTokens($candidate->name);
                if (empty($candidateTokens)) {
                    continue;
                }

                $score = $this->tokenSimilarity($primaryTokens, $candidateTokens);

                // Also check substring containment for extra signal
                $primaryLower = mb_strtolower($primary->name);
                $candidateLower = mb_strtolower($candidate->name);
                if (str_contains($primaryLower, $candidateLower) || str_contains($candidateLower, $primaryLower)) {
                    $score = max($score, 0.7);
                }

                if ($score >= self::MERGE_SIMILARITY_THRESHOLD) {
                    $this->mergeThreads($primary, $candidate);
                    $consumed[] = $candidate->id;
                    $merged++;
                }
            }
        }

        return $merged;
    }

    /**
     * Promote emerging conflicts: threads that accumulated many events quickly
     * but lack a proper summary or are sub-threads that should be top-level.
     */
    public function promoteEmergingConflicts(): int
    {
        $cutoff = now()->subHours(self::PROMOTION_HOURS);

        // Find threads created recently with high event volume
        $candidates = ConflictThread::open()
            ->where('created_at', '>=', $cutoff)
            ->withCount('events')
            ->get()
            ->filter(fn ($t) => $t->events_count >= self::PROMOTION_EVENT_THRESHOLD);

        $promoted = 0;

        foreach ($candidates as $thread) {
            $needsUpdate = false;

            // Generate summary if missing or very short
            if (! $thread->summary || mb_strlen($thread->summary) < 50) {
                $needsUpdate = true;
            }

            // If it's a sub-thread with very high volume, consider promoting to top-level
            if ($thread->parent_id !== null && $thread->events_count >= self::PROMOTION_EVENT_THRESHOLD * 2) {
                $thread->update(['parent_id' => null]);
                Log::info('Sub-thread promoted to top-level conflict', [
                    'thread_id' => $thread->id,
                    'thread_name' => $thread->name,
                    'event_count' => $thread->events_count,
                ]);
            }

            if ($needsUpdate && $this->apiKey) {
                try {
                    $summary = $this->generateThreadSummary($thread);
                    if ($summary) {
                        $thread->update(['summary' => $summary]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Thread summary generation failed', [
                        'thread_id' => $thread->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $promoted++;
        }

        return $promoted;
    }

    /**
     * Refresh summaries of active top-level threads that haven't been updated recently.
     * Only updates threads with significant recent activity.
     */
    public function refreshStaleSummaries(): int
    {
        if (! $this->apiKey) {
            return 0;
        }

        // Top-level threads with events in the last 24h, sorted by event volume
        $threads = ConflictThread::open()
            ->topLevel()
            ->whereHas('events', fn ($q) => $q->where('created_at', '>=', now()->subHours(24)))
            ->where(function ($q) {
                $q->whereNull('updated_at')
                  ->orWhere('updated_at', '<', now()->subHours(6));
            })
            ->orderByDesc('max_severity')
            ->limit(10)
            ->get();

        $updated = 0;

        foreach ($threads as $thread) {
            try {
                $summary = $this->generateThreadSummary($thread);
                if ($summary) {
                    $thread->update(['summary' => $summary]);
                    $updated++;
                }
            } catch (\Throwable $e) {
                Log::warning('Thread summary refresh failed', [
                    'thread_id' => $thread->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $updated;
    }

    // ── Private helpers ──

    private function mergeThreads(ConflictThread $primary, ConflictThread $duplicate): void
    {
        DB::transaction(function () use ($primary, $duplicate) {
            // Move all events from duplicate to primary
            Event::where('conflict_thread_id', $duplicate->id)
                ->update(['conflict_thread_id' => $primary->id]);

            // Move child threads from duplicate to primary
            ConflictThread::where('parent_id', $duplicate->id)
                ->update(['parent_id' => $primary->id]);

            // Close the duplicate
            $duplicate->update(['status' => 'closed']);
        });

        Log::info('Duplicate threads merged', [
            'primary_id' => $primary->id,
            'primary_name' => $primary->name,
            'duplicate_id' => $duplicate->id,
            'duplicate_name' => $duplicate->name,
        ]);
    }

    private function generateThreadSummary(ConflictThread $thread): ?string
    {
        $allThreadIds = $thread->children()->pluck('id')->push($thread->id);

        $recentEvents = Event::whereIn('conflict_thread_id', $allThreadIds)
            ->whereNotIn('status', ['pending_classification', 'retracted'])
            ->orderByDesc('severity')
            ->limit(20)
            ->get(['title', 'summary', 'category', 'severity', 'country', 'occurred_at']);

        if ($recentEvents->isEmpty()) {
            return null;
        }

        $eventsJson = $recentEvents->map(fn ($e) => [
            'title' => $e->title,
            'summary' => $e->summary,
            'category' => $e->category,
            'severity' => $e->severity,
            'country' => $e->country,
        ])->toJson(JSON_UNESCAPED_UNICODE);

        $countries = $recentEvents->pluck('country')->filter()->unique()->implode(', ');

        $prompt = <<<PROMPT
You are a conflict intelligence analyst. Write a concise summary (2-4 sentences) for the conflict thread "{$thread->name}".

The summary should:
- Describe the nature and scope of the conflict
- Note the primary parties involved
- Mention the current phase or trajectory (escalating, de-escalating, stalemate)
- Be factual and neutral — no editorializing

Countries involved: {$countries}

Recent events in this conflict:
{$eventsJson}

Return ONLY the summary text, no JSON, no formatting, no quotes.
PROMPT;

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 512,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Summary generation failed: HTTP {$response->status()}");
        }

        $content = trim($response->json('choices.0.message.content', ''));

        // Strip any wrapping quotes the LLM might add
        $content = trim($content, '"\'');

        return $content ?: null;
    }

    private function normalizeTokens(string $text): array
    {
        $text = mb_strtolower($text);
        $words = preg_split('/\W+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $stopWords = ['the', 'a', 'an', 'in', 'on', 'at', 'to', 'of', 'and', 'or', 'is', 'was', 'are',
            'were', 'has', 'have', 'had', 'for', 'with', 'from', 'by', 'about', 'this', 'that',
            'it', 'its', 'not', 'but', 'if', 'all', 'any', 'both', 'each', 'other', 'some'];

        $tokens = array_values(array_filter($words, fn ($w) => strlen($w) > 2 && ! in_array($w, $stopWords)));
        sort($tokens);

        return array_values(array_unique($tokens));
    }

    private function tokenSimilarity(array $tokensA, array $tokensB): float
    {
        $intersection = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));

        return $union > 0 ? $intersection / $union : 0.0;
    }
}
