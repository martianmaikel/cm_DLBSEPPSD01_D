<?php

namespace App\Services\ThreatLevel;

use App\Contracts\ThreatLevelProvider;
use App\Models\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WorldThreatLevelService
{
    private const CACHE_KEY_FULL = 'threat_level:full';
    private const CACHE_KEY_SCORE = 'threat_level:score';
    private const CACHE_TTL_FULL = 1800;  // 30 minutes
    private const CACHE_TTL_SCORE = 300;  // 5 minutes
    private const SCORE_SHIFT_THRESHOLD = 2;
    private const SUMMARY_MAX_AGE = 3600;  // Regenerate summary after 1 hour regardless of score shift

    private const STATUS_WEIGHTS = [
        'confirmed' => 1.0,
        'corroborated' => 0.9,
        'unverified' => 0.6,
        'pending_classification' => 0.4,
        'disputed' => 0.3,
        'retracted' => 0.0,
    ];

    public function __construct(
        private ThreatLevelProvider $provider,
    ) {}

    /**
     * Full computation: score + LLM summary (called by scheduled command).
     */
    public function compute(bool $force = false): array
    {
        $scoreData = $this->computeScoreWithContext();
        $level = $scoreData['level'];

        // Check if we can reuse the cached summary
        if (! $force) {
            $cached = Cache::get(self::CACHE_KEY_FULL);
            if ($cached) {
                $scoreDrift = abs($cached['level'] - $level);
                $summaryAge = isset($cached['summary_generated_at'])
                    ? now()->diffInSeconds($cached['summary_generated_at'])
                    : PHP_INT_MAX;
                $summaryStale = $summaryAge >= self::SUMMARY_MAX_AGE;

                if ($scoreDrift < self::SCORE_SHIFT_THRESHOLD && ! $summaryStale) {
                    // Score hasn't shifted enough and summary is still fresh — reuse summary, update score + stats
                    $cached['level'] = $level;
                    $cached['statistics'] = $scoreData['statistics'];
                    $cached['generated_at'] = now()->toIso8601String();
                    Cache::put(self::CACHE_KEY_FULL, $cached, self::CACHE_TTL_FULL);
                    Cache::put(self::CACHE_KEY_SCORE, $scoreData, self::CACHE_TTL_SCORE);

                    return $cached;
                }
            }
        }

        // Generate new LLM summaries (single bilingual call)
        $context = array_merge(['level' => $level], $scoreData['statistics']);

        try {
            $bilingualResult = $this->provider->generateBilingualSummary($context);

            $result = [
                'level' => $level,
                'label_en' => $bilingualResult->labelEn,
                'label_de' => $bilingualResult->labelDe,
                'summary_en' => $bilingualResult->summaryEn,
                'summary_de' => $bilingualResult->summaryDe,
                'statistics' => $scoreData['statistics'],
                'generated_at' => now()->toIso8601String(),
                'summary_generated_at' => now()->toIso8601String(),
            ];

            Cache::put(self::CACHE_KEY_FULL, $result, self::CACHE_TTL_FULL);
            Cache::put(self::CACHE_KEY_SCORE, $scoreData, self::CACHE_TTL_SCORE);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Threat level LLM summary failed, falling back to score-only', [
                'error' => $e->getMessage(),
            ]);

            // Cache score-only result so API still responds
            $fallback = $this->buildScoreOnlyResult($level, $scoreData['statistics']);
            Cache::put(self::CACHE_KEY_SCORE, $scoreData, self::CACHE_TTL_SCORE);

            return $fallback;
        }
    }

    /**
     * Score-only computation (no LLM call). Used as API fallback.
     */
    public function computeScoreOnly(): array
    {
        $scoreData = $this->computeScoreWithContext();

        return $this->buildScoreOnlyResult($scoreData['level'], $scoreData['statistics']);
    }

    private function buildScoreOnlyResult(int $level, array $statistics): array
    {
        return [
            'level' => $level,
            'label_en' => $this->defaultLabel($level, 'en'),
            'label_de' => $this->defaultLabel($level, 'de'),
            'summary_en' => null,
            'summary_de' => null,
            'statistics' => $statistics,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Compute the numeric threat level score and gather context data.
     */
    private function computeScoreWithContext(): array
    {
        $currentEvents = Event::recent(24)
            ->whereNotIn('status', ['retracted'])
            ->select(['severity', 'confidence', 'status', 'country', 'category', 'title'])
            ->get();

        $previousEvents = Event::where('occurred_at', '>=', now()->subHours(48))
            ->where('occurred_at', '<', now()->subHours(24))
            ->whereNotIn('status', ['retracted'])
            ->select(['severity', 'confidence', 'status'])
            ->get();

        // Edge case: no events
        if ($currentEvents->isEmpty()) {
            return [
                'level' => 1,
                'statistics' => [
                    'total_events' => 0,
                    'avg_severity' => 0,
                    'max_severity' => 0,
                    'active_zones' => 0,
                    'escalation_trend' => 'stable',
                    'escalation_delta_pct' => 0,
                    'top_countries' => [],
                    'top_events' => [],
                    'category_breakdown' => [],
                ],
            ];
        }

        // Compute weighted severities for current period
        $currentWeights = $currentEvents->map(fn ($e) => $this->weightedSeverity($e))->filter(fn ($w) => $w > 0)->values();
        $previousWeights = $previousEvents->map(fn ($e) => $this->weightedSeverity($e))->filter(fn ($w) => $w > 0)->values();

        // Factor 1: 90th percentile of weighted severities (weight 0.40)
        $p90 = $this->percentile($currentWeights->toArray(), 90);
        $severityP90Factor = min(10, max(1, $p90));

        // Factor 2: Active conflict zones — distinct countries with severity >= 5 (weight 0.25)
        $activeZones = $currentEvents->filter(fn ($e) => $e->severity >= 5)
            ->pluck('country')
            ->filter()
            ->unique()
            ->count();
        $zonesFactor = min(10, $activeZones * 1.5);

        // Factor 3: Escalation trend vs previous period (weight 0.20)
        $currentAvg = $currentWeights->avg() ?: 0;
        $previousAvg = $previousWeights->avg() ?: 0;
        $escalationDelta = $previousAvg > 0
            ? ($currentAvg - $previousAvg) / $previousAvg
            : ($currentAvg > 0 ? 0.5 : 0);
        $escalationFactor = max(1, min(10, 5 + $escalationDelta * 3));

        // Factor 4: Max severity among confirmed/corroborated events (weight 0.15)
        $maxSeverity = $currentEvents
            ->filter(fn ($e) => in_array($e->status, ['confirmed', 'corroborated']))
            ->max('severity') ?? $currentEvents->max('severity') ?? 1;
        $maxSeverityFactor = min(10, max(1, $maxSeverity));

        // Weighted combination
        $baseScore = ($severityP90Factor * 0.40)
            + ($zonesFactor * 0.25)
            + ($escalationFactor * 0.20)
            + ($maxSeverityFactor * 0.15);

        $level = (int) max(1, min(10, round($baseScore)));

        // Escalation trend label
        $escalationTrend = match (true) {
            $escalationDelta > 0.1 => 'rising',
            $escalationDelta < -0.1 => 'falling',
            default => 'stable',
        };

        // Top countries by event count
        $topCountries = $currentEvents->groupBy('country')
            ->filter(fn ($g, $key) => $key !== null && $key !== '')
            ->sortByDesc(fn ($g) => $g->count())
            ->keys()
            ->take(5)
            ->values()
            ->toArray();

        // Top events by weighted severity
        $topEvents = $currentEvents->sortByDesc(fn ($e) => $this->weightedSeverity($e))
            ->take(5)
            ->map(fn ($e) => [
                'title' => $e->title,
                'severity' => $e->severity,
                'country' => $e->country,
                'category' => $e->category,
            ])
            ->values()
            ->toArray();

        // Category breakdown
        $categoryBreakdown = $currentEvents->groupBy('category')
            ->map(fn ($g, $cat) => ['category' => $cat, 'count' => $g->count()])
            ->sortByDesc('count')
            ->values()
            ->toArray();

        return [
            'level' => $level,
            'statistics' => [
                'total_events' => $currentEvents->count(),
                'avg_severity' => round($currentEvents->avg('severity'), 1),
                'max_severity' => (int) $maxSeverity,
                'active_zones' => $activeZones,
                'escalation_trend' => $escalationTrend,
                'escalation_delta_pct' => round($escalationDelta * 100, 1),
                'top_countries' => $topCountries,
                'top_events' => $topEvents,
                'category_breakdown' => $categoryBreakdown,
            ],
        ];
    }

    private function weightedSeverity(Event $event): float
    {
        $statusWeight = self::STATUS_WEIGHTS[$event->status] ?? 0.5;
        $confidenceMultiplier = ($event->confidence ?? 5) / 10;

        return $event->severity * $confidenceMultiplier * $statusWeight;
    }

    private function percentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        $fraction = $index - $lower;

        if ($lower === $upper) {
            return $values[$lower];
        }

        return $values[$lower] + $fraction * ($values[$upper] - $values[$lower]);
    }

    private function defaultLabel(int $level, string $locale): string
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
}
