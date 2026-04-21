<?php

namespace App\Services\Threading;

use App\Models\ConflictThread;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ThreadMatchingService
{
    private const HIGH_MATCH_THRESHOLD = 0.65;
    private const SUGGESTION_THRESHOLD = 0.45;
    private const MIN_SEVERITY_FOR_NEW_THREAD = 3;

    /** Minimum word-token overlap ratio to consider a fuzzy conflict_context match. */
    private const CONTEXT_FUZZY_THRESHOLD = 0.5;

    public function assignThread(Event $event): void
    {
        if ($event->conflict_thread_id !== null) {
            return;
        }

        $conflictContext = $event->entities_json['conflict_context'] ?? null;

        // Try conflict_context fuzzy matching first — most reliable signal
        if ($conflictContext) {
            $contextMatch = $this->findThreadByConflictContext($conflictContext);

            if ($contextMatch) {
                $event->update(['conflict_thread_id' => $contextMatch->id]);
                Log::info('Event assigned to thread via conflict_context', [
                    'event_id' => $event->id,
                    'thread_id' => $contextMatch->id,
                    'conflict_context' => $conflictContext,
                    'thread_name' => $contextMatch->name,
                ]);

                return;
            }
        }

        $openThreads = ConflictThread::open()->get();

        if ($openThreads->isEmpty()) {
            $this->maybeCreateThread($event, $conflictContext);

            return;
        }

        $bestThread = null;
        $bestScore = 0.0;

        foreach ($openThreads as $thread) {
            $score = $this->computeThreadSimilarity($event, $thread);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestThread = $thread;
            }
        }

        if ($bestScore >= self::HIGH_MATCH_THRESHOLD && $bestThread !== null) {
            $event->update(['conflict_thread_id' => $bestThread->id]);
            Log::info('Event assigned to thread via similarity', [
                'event_id' => $event->id,
                'thread_id' => $bestThread->id,
                'score' => round($bestScore, 4),
            ]);

            return;
        }

        if ($bestScore > self::SUGGESTION_THRESHOLD && $bestThread !== null) {
            $existing = $event->entities_json ?? [];
            $existing['thread_suggestion'] = [
                'thread_id' => $bestThread->id,
                'thread_name' => $bestThread->name,
                'score' => round($bestScore, 4),
            ];
            $event->update(['entities_json' => $existing]);

            return;
        }

        $this->maybeCreateThread($event, $conflictContext);
    }

    /**
     * Find a thread by fuzzy-matching the conflict_context string.
     *
     * Tries: exact match → normalized token overlap → substring containment.
     */
    private function findThreadByConflictContext(string $conflictContext): ?ConflictThread
    {
        // 1. Exact match
        $exact = ConflictThread::open()
            ->where('name', $conflictContext)
            ->first();

        if ($exact) {
            return $exact;
        }

        // 2. Case-insensitive match
        $ilike = ConflictThread::open()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($conflictContext)])
            ->first();

        if ($ilike) {
            return $ilike;
        }

        // 3. Normalized token-based fuzzy matching against all open threads
        $contextTokens = $this->normalizeTokens($conflictContext);

        if (empty($contextTokens)) {
            return null;
        }

        $openThreads = ConflictThread::open()->get();
        $bestThread = null;
        $bestScore = 0.0;

        foreach ($openThreads as $thread) {
            $threadTokens = $this->normalizeTokens($thread->name);

            if (empty($threadTokens)) {
                continue;
            }

            // Jaccard similarity on meaningful tokens
            $intersection = count(array_intersect($contextTokens, $threadTokens));
            $union = count(array_unique(array_merge($contextTokens, $threadTokens)));
            $jaccard = $union > 0 ? $intersection / $union : 0.0;

            // Also check bi-directional substring containment
            $contextStr = mb_strtolower($conflictContext);
            $threadStr = mb_strtolower($thread->name);
            $substringBonus = 0.0;
            if (str_contains($contextStr, $threadStr) || str_contains($threadStr, $contextStr)) {
                $substringBonus = 0.3;
            }

            $score = $jaccard + $substringBonus;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestThread = $thread;
            }
        }

        if ($bestScore >= self::CONTEXT_FUZZY_THRESHOLD && $bestThread !== null) {
            return $bestThread;
        }

        return null;
    }

    private function computeThreadSimilarity(Event $event, ConflictThread $thread): float
    {
        $embeddingScore = $this->computeEmbeddingSimilarityToThread($event, $thread);
        $textScore = $this->computeTextOverlap($event, $thread);
        $contextScore = $this->computeConflictContextSimilarity($event, $thread);

        // Use the best available signal, boosted by context similarity
        $baseScore = max(
            $embeddingScore ?? 0.0,
            $textScore
        );

        // Context similarity provides a strong boost when present
        if ($contextScore > 0.3) {
            $baseScore = max($baseScore, $contextScore * 0.9);
        }

        return $baseScore;
    }

    /**
     * Compare event's conflict_context against thread name using token overlap.
     */
    private function computeConflictContextSimilarity(Event $event, ConflictThread $thread): float
    {
        $conflictContext = $event->entities_json['conflict_context'] ?? null;

        if (! $conflictContext) {
            return 0.0;
        }

        $contextTokens = $this->normalizeTokens($conflictContext);
        $threadTokens = $this->normalizeTokens($thread->name);

        if (empty($contextTokens) || empty($threadTokens)) {
            return 0.0;
        }

        $intersection = count(array_intersect($contextTokens, $threadTokens));
        $union = count(array_unique(array_merge($contextTokens, $threadTokens)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    private function computeEmbeddingSimilarityToThread(Event $event, ConflictThread $thread): ?float
    {
        $threadEventIds = $thread->events()->pluck('id');

        if ($threadEventIds->isEmpty()) {
            return null;
        }

        try {
            $result = DB::selectOne(
                "SELECT 1 - (e_new.vector <=> e_thread.vector) AS similarity
                 FROM embeddings e_new
                 JOIN embeddings e_thread ON e_thread.event_id = ANY(?)
                   AND e_thread.provider = e_new.provider
                 WHERE e_new.event_id = ?
                 ORDER BY similarity DESC
                 LIMIT 1",
                ['{' . $threadEventIds->implode(',') . '}', $event->id]
            );

            return $result ? (float) $result->similarity : null;
        } catch (\Throwable $e) {
            Log::debug('Thread embedding similarity query failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function computeTextOverlap(Event $event, ConflictThread $thread): float
    {
        $eventWords = $this->tokenize($event->title . ' ' . $event->summary);
        $threadWords = $this->tokenize($thread->name . ' ' . $thread->summary);

        if (empty($eventWords) || empty($threadWords)) {
            return 0.0;
        }

        $intersection = count(array_intersect($eventWords, $threadWords));
        $union = count(array_unique(array_merge($eventWords, $threadWords)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $words = preg_split('/\W+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $stopWords = ['the', 'a', 'an', 'in', 'on', 'at', 'to', 'of', 'and', 'or', 'is', 'was', 'are', 'were',
            'has', 'have', 'had', 'be', 'been', 'being', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'may', 'might', 'shall', 'can', 'for', 'with', 'from', 'by', 'about', 'into', 'through', 'during',
            'before', 'after', 'above', 'below', 'between', 'under', 'over', 'this', 'that', 'these', 'those',
            'it', 'its', 'not', 'no', 'but', 'if', 'then', 'than', 'so', 'up', 'out', 'just', 'also', 'very',
            'all', 'any', 'each', 'every', 'both', 'few', 'more', 'most', 'other', 'some', 'such', 'only'];

        return array_values(array_filter($words, fn ($w) => strlen($w) > 2 && ! in_array($w, $stopWords)));
    }

    /**
     * Normalize a string into sortable meaningful tokens for fuzzy comparison.
     * Strips punctuation, lowercases, removes stop words, and sorts alphabetically.
     */
    private function normalizeTokens(string $text): array
    {
        $tokens = $this->tokenize($text);
        sort($tokens);

        return array_values(array_unique($tokens));
    }

    private function maybeCreateThread(Event $event, ?string $conflictContext = null): void
    {
        if ($event->severity < self::MIN_SEVERITY_FOR_NEW_THREAD) {
            return;
        }

        // Always prefer the LLM-provided conflict context as thread name
        $threadName = $conflictContext ?? $this->generateThreadName($event);

        // Check if a thread with this generated name already exists (avoid near-dupes)
        $existing = $this->findThreadByConflictContext($threadName);
        if ($existing) {
            $event->update(['conflict_thread_id' => $existing->id]);
            Log::info('Event assigned to existing thread (name match on create)', [
                'event_id' => $event->id,
                'thread_id' => $existing->id,
            ]);

            return;
        }

        // For high-severity events (5+), create as top-level thread
        // For lower severity, create as sub-thread under a matching parent if possible
        $parentId = null;
        if ($event->severity < 5 && $conflictContext) {
            $parentMatch = $this->findTopLevelThreadByContext($conflictContext);
            if ($parentMatch) {
                $parentId = $parentMatch->id;
            }
        }

        $thread = ConflictThread::create([
            'name' => $threadName,
            'summary' => $event->summary,
            'status' => 'open',
            'parent_id' => $parentId,
        ]);

        $event->update(['conflict_thread_id' => $thread->id]);

        Log::info('New conflict thread created', [
            'thread_id' => $thread->id,
            'thread_name' => $thread->name,
            'parent_id' => $parentId,
            'event_id' => $event->id,
        ]);
    }

    /**
     * Find a top-level thread matching the given conflict context.
     */
    private function findTopLevelThreadByContext(string $context): ?ConflictThread
    {
        $contextTokens = $this->normalizeTokens($context);

        if (empty($contextTokens)) {
            return null;
        }

        $topLevelThreads = ConflictThread::open()->topLevel()->get();
        $bestThread = null;
        $bestScore = 0.0;

        foreach ($topLevelThreads as $thread) {
            $threadTokens = $this->normalizeTokens($thread->name);

            if (empty($threadTokens)) {
                continue;
            }

            $intersection = count(array_intersect($contextTokens, $threadTokens));
            $union = count(array_unique(array_merge($contextTokens, $threadTokens)));
            $score = $union > 0 ? $intersection / $union : 0.0;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestThread = $thread;
            }
        }

        return $bestScore >= self::CONTEXT_FUZZY_THRESHOLD ? $bestThread : null;
    }

    private function generateThreadName(Event $event): string
    {
        $location = $event->region ?? $event->country ?? 'Unknown Region';
        $category = ucfirst(str_replace('_', ' ', $event->category ?? 'Incident'));

        return "{$location} {$category}";
    }
}
