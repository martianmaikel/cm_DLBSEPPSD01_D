<?php

namespace App\Services\Briefing;

use App\Contracts\BriefingProvider;
use App\Models\ConflictThread;
use App\Models\DailyBriefing;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DailyBriefingService
{
    public function __construct(
        private BriefingProvider $provider,
    ) {}

    public function generate(Carbon $date, bool $force = false): ?DailyBriefing
    {
        // Check if briefing already exists for this date
        $existing = DailyBriefing::forDate($date)->first();
        if ($existing && ! $force) {
            Log::info("Briefing already exists for {$date->toDateString()}, skipping.");
            return $existing;
        }

        // Gather events from the past 24 hours
        $events = Event::where('occurred_at', '>=', $date->copy()->subDay())
            ->where('occurred_at', '<=', $date->copy()->endOfDay())
            ->with('conflictThread:id,name')
            ->select(['id', 'title', 'summary', 'severity', 'category', 'country', 'status', 'conflict_thread_id'])
            ->orderByDesc('severity')
            ->limit(200)
            ->get();

        if ($events->isEmpty()) {
            Log::info("No events found for {$date->toDateString()}, skipping briefing.");
            return null;
        }

        $eventSummaries = $events->map(fn(Event $e) => [
            'title' => $e->title,
            'summary' => $e->summary,
            'severity' => $e->severity,
            'category' => $e->category,
            'country' => $e->country,
            'status' => $e->status,
            'conflict_thread' => $e->conflictThread?->name,
        ])->toArray();

        $threads = ConflictThread::withCount(['events as event_count_24h' => function ($q) use ($date) {
                $q->where('occurred_at', '>=', $date->copy()->subDay())
                    ->where('occurred_at', '<=', $date->copy()->endOfDay());
            }])
            ->withCount('events')
            ->withMax('events', 'severity')
            ->whereHas('events', function ($q) use ($date) {
                $q->where('occurred_at', '>=', $date->copy()->subDay())
                    ->where('occurred_at', '<=', $date->copy()->endOfDay());
            })
            ->get()
            ->map(fn(ConflictThread $t) => [
                'name' => $t->name,
                'summary' => $t->summary,
                'event_count_total' => $t->events_count,
                'event_count_24h' => $t->event_count_24h,
                'max_severity' => (int) $t->events_max_severity,
            ])->toArray();

        // Compute day-over-day comparison context
        $comparison = $this->buildComparisonContext($date, $events, $threads);

        // Generate bilingual briefing in a single LLM call
        $result = $this->provider->generateBilingualBriefing($eventSummaries, $threads, $comparison);

        // Compute statistics locally (more reliable than LLM-generated stats)
        $stats = [
            'total_events' => $events->count(),
            'avg_severity' => round($events->avg('severity'), 1),
            'top_categories' => $events->groupBy('category')
                ->sortByDesc(fn($g) => $g->count())
                ->keys()
                ->take(3)
                ->values()
                ->toArray(),
            'top_countries' => $events->groupBy('country')
                ->sortByDesc(fn($g) => $g->count())
                ->keys()
                ->filter()
                ->take(3)
                ->values()
                ->toArray(),
            'new_threads' => ConflictThread::whereDate('created_at', $date)->count(),
            'comparison' => $comparison,
        ];

        // Merge bilingual conflict sections into one structure
        $conflictSections = [
            'en' => $result->conflictSectionsEn,
            'de' => $result->conflictSectionsDe,
        ];

        // Store or update
        $briefing = DailyBriefing::updateOrCreate(
            ['briefing_date' => $date->toDateString()],
            [
                'title' => $result->titleEn,
                'summary_en' => $result->summaryEn,
                'summary_de' => $result->summaryDe,
                'key_developments' => $result->keyDevelopmentsEn,
                'conflict_sections' => $conflictSections,
                'statistics' => $stats,
                'generated_by' => config('llm.default_classifier'),
                'generated_at' => now(),
            ]
        );

        Log::info("Daily briefing generated for {$date->toDateString()}");

        return $briefing;
    }

    /**
     * Build day-over-day comparison metrics for the LLM prompt.
     */
    private function buildComparisonContext(Carbon $date, $currentEvents, array $currentThreads): array
    {
        $previousStart = $date->copy()->subDays(2);
        $previousEnd = $date->copy()->subDay();

        // Previous period events
        $previousEvents = Event::where('occurred_at', '>=', $previousStart)
            ->where('occurred_at', '<=', $previousEnd)
            ->select(['severity', 'category', 'country', 'conflict_thread_id'])
            ->get();

        // Previous day's briefing for narrative continuity
        $previousBriefing = DailyBriefing::forDate($previousEnd)->first();

        $currentCount = $currentEvents->count();
        $previousCount = $previousEvents->count();
        $currentAvgSeverity = round($currentEvents->avg('severity'), 1);
        $previousAvgSeverity = $previousEvents->isNotEmpty() ? round($previousEvents->avg('severity'), 1) : null;

        // Event count trend
        $eventCountChangePct = $previousCount > 0
            ? round(($currentCount - $previousCount) / $previousCount * 100, 1)
            : null;

        // Severity trend
        $severityDelta = ($previousAvgSeverity !== null)
            ? round($currentAvgSeverity - $previousAvgSeverity, 1)
            : null;
        $severityTrend = match (true) {
            $severityDelta === null => 'unknown',
            $severityDelta > 0.3 => 'rising',
            $severityDelta < -0.3 => 'falling',
            default => 'stable',
        };

        // Country-level hotspot analysis
        $currentCountries = $currentEvents->pluck('country')->filter()->unique()->values();
        $previousCountries = $previousEvents->pluck('country')->filter()->unique()->values();
        $newHotspots = $currentCountries->diff($previousCountries)->values()->toArray();
        $coolingHotspots = $previousCountries->diff($currentCountries)->values()->toArray();

        // Per-thread trend analysis
        $previousThreadCounts = $previousEvents->groupBy('conflict_thread_id')
            ->map(fn($g) => $g->count());

        // Pre-load thread name→id mapping in a single query
        $threadNames = collect($currentThreads)->pluck('name')->filter()->toArray();
        $threadNameToId = ConflictThread::whereIn('name', $threadNames)
            ->pluck('id', 'name');

        $threadTrends = collect($currentThreads)
            ->map(function ($thread) use ($previousThreadCounts, $threadNameToId) {
                $currentCount = $thread['event_count_24h'];
                $threadId = $threadNameToId->get($thread['name']);
                $prevCount = $threadId ? ($previousThreadCounts->get($threadId) ?? 0) : 0;

                if ($prevCount === 0 && $currentCount > 0) {
                    $trend = 'new';
                } elseif ($prevCount > 0) {
                    $changePct = ($currentCount - $prevCount) / $prevCount * 100;
                    $trend = match (true) {
                        $changePct > 20 => 'escalating',
                        $changePct < -20 => 'de-escalating',
                        default => 'stable',
                    };
                } else {
                    $trend = 'stable';
                }

                return [
                    'name' => $thread['name'],
                    'current_events' => $currentCount,
                    'previous_events' => $prevCount,
                    'trend' => $trend,
                ];
            })
            ->filter(fn($t) => $t['current_events'] >= 3 || $t['previous_events'] >= 3)
            ->sortByDesc('current_events')
            ->values()
            ->toArray();

        // Category shift analysis
        $currentCategories = $currentEvents->groupBy('category')->map(fn($g) => $g->count());
        $previousCategories = $previousEvents->groupBy('category')->map(fn($g) => $g->count());
        $categoryShifts = $currentCategories->map(function ($count, $category) use ($previousCategories) {
            $prevCount = $previousCategories->get($category, 0);
            if ($prevCount === 0 && $count > 0) {
                return ['category' => $category, 'current' => $count, 'previous' => 0, 'trend' => 'new'];
            }
            $changePct = $prevCount > 0 ? ($count - $prevCount) / $prevCount * 100 : 0;
            $trend = match (true) {
                $changePct > 30 => 'increasing',
                $changePct < -30 => 'decreasing',
                default => 'stable',
            };

            return ['category' => $category, 'current' => $count, 'previous' => $prevCount, 'trend' => $trend];
        })->filter(fn($c) => $c['trend'] !== 'stable')->values()->toArray();

        $comparison = array_filter([
            'has_previous_data' => $previousEvents->isNotEmpty(),
            'previous_date' => $previousEnd->toDateString(),
            'previous_total_events' => $previousCount,
            'current_total_events' => $currentCount,
            'event_count_change_pct' => $eventCountChangePct,
            'previous_avg_severity' => $previousAvgSeverity,
            'current_avg_severity' => $currentAvgSeverity,
            'severity_delta' => $severityDelta,
            'severity_trend' => $severityTrend,
            'thread_trends' => $threadTrends,
            'new_hotspots' => $newHotspots,
            'cooling_hotspots' => $coolingHotspots,
            'category_shifts' => $categoryShifts,
        ], fn($v) => $v !== null && $v !== []);

        // Previous summaries are passed separately to the prompt (not in the JSON block)
        if ($previousBriefing?->summary_en) {
            $comparison['previous_summary_en'] = $previousBriefing->summary_en;
        }
        if ($previousBriefing?->summary_de) {
            $comparison['previous_summary_de'] = $previousBriefing->summary_de;
        }

        return $comparison;
    }
}
