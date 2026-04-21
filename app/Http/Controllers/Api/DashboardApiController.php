<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConflictThread;
use App\Models\DailyBriefing;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $timeRange = $request->input('time_range', '24h');
        $hours = match ($timeRange) {
            '48h' => 48,
            '7d' => 168,
            'all' => 8760,
            default => 24,
        };

        $query = Event::recent($hours)
            ->where('status', '!=', 'pending_classification')
            ->with('source')
            ->orderByDesc('occurred_at');

        if ($request->filled('severity_min')) {
            $query->where('severity', '>=', (int) $request->input('severity_min'));
        }
        if ($request->filled('severity_max')) {
            $query->where('severity', '<=', (int) $request->input('severity_max'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('region')) {
            $countryCodes = $this->regionToCountryCodes($request->input('region'));
            if ($countryCodes) {
                $query->whereIn('country', $countryCodes);
            }
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('country')) {
            $query->where('country', $request->input('country'));
        }

        $mapEvent = fn(Event $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'title_de' => $e->title_de,
            'summary' => $e->summary,
            'summary_de' => $e->summary_de,
            'severity' => $e->severity,
            'severity_factors' => $e->severity_factors,
            'confidence' => $e->confidence,
            'status' => $e->status,
            'category' => $e->category,
            'country' => $e->country,
            'country_code' => $e->country,
            'region' => $e->region,
            'coordinates' => $e->coordinates,
            'occurred_at' => $e->occurred_at?->toIso8601String(),
            'source_name' => $e->source?->name,
            'source_url' => $e->source_url ?: $e->source?->url,
            'source_reliability' => $e->source?->reliability_score,
            'entities_json' => $e->entities_json,
            'conflict_thread_id' => $e->conflict_thread_id,
            'corroboration_count' => $e->corroboration_count,
        ];

        $events = $query->limit(500)->get()->map($mapEvent);

        $threads = ConflictThread::with(['children' => function ($q) {
                $q->open()
                    ->withCount('events')
                    ->withMax('events', 'occurred_at')
                    ->whereHas('events')
                    ->orderByDesc('events_max_occurred_at');
            }])
            ->topLevel()
            ->open()
            ->withCount('events')
            ->withMax('events', 'severity')
            ->withMax('events', 'occurred_at')
            ->orderByDesc('events_max_occurred_at')
            ->get()
            ->filter(function (ConflictThread $t) {
                // Show if thread has direct events or children with events
                return $t->events_count > 0 || $t->children->isNotEmpty();
            })
            ->map(fn(ConflictThread $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'summary' => $t->summary,
                'status' => $t->status,
                'parent_id' => $t->parent_id,
                'event_count_total' => $t->event_count_total ?: $t->events_count,
                'event_count_24h' => $t->event_count_24h,
                'max_severity' => $t->max_severity ?: (int) $t->events_max_severity,
                'latest_event_at' => $t->events_max_occurred_at,
                'countries' => $t->countries ?? [],
                'categories' => $t->categories ?? [],
                'sub_thread_count' => $t->sub_thread_count,
                'children' => $t->children->map(fn(ConflictThread $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'summary' => $c->summary,
                    'event_count' => $c->events_count,
                    'max_severity' => $c->max_severity ?: 0,
                    'latest_event_at' => $c->events_max_occurred_at,
                ]),
            ])->values();

        // Backfill: load recent events for threads that have no events in the time-filtered response
        $coveredThreadIds = $events->pluck('conflict_thread_id')->filter()->unique();

        $allThreadIds = $threads->pluck('id')
            ->merge($threads->flatMap(fn($t) => collect($t['children'])->pluck('id')))
            ->unique();

        $uncoveredThreadIds = $allThreadIds->diff($coveredThreadIds)->values();

        if ($uncoveredThreadIds->isNotEmpty()) {
            $backfillEvents = Event::whereIn('conflict_thread_id', $uncoveredThreadIds)
                ->with('source')
                ->orderByDesc('occurred_at')
                ->limit(200)
                ->get()
                ->map($mapEvent);

            $events = $events->concat($backfillEvents);
        }

        return response()->json([
            'events' => $events,
            'threads' => $threads,
        ]);
    }

    public function ticker(): JsonResponse
    {
        $events = Event::recent(2)
            ->where('severity', '>=', 5)
            ->with('source')
            ->orderByDesc('occurred_at')
            ->limit(30)
            ->get()
            ->map(fn(Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'title_de' => $e->title_de,
                'severity' => $e->severity,
                'country' => $e->country,
                'category' => $e->category,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ]);

        return response()->json($events);
    }

    public function briefing(Request $request): JsonResponse
    {
        $query = DailyBriefing::query();

        if ($request->filled('date')) {
            $query->whereDate('briefing_date', $request->input('date'));
        } else {
            $query->latest('briefing_date');
        }

        return response()->json($query->first());
    }

    public function briefings(Request $request): JsonResponse
    {
        $limit = min((int) ($request->input('limit', 7)), 30);

        $briefings = DailyBriefing::latest('briefing_date')
            ->whereNotNull('summary_en')
            ->where('summary_en', '!=', '')
            ->limit($limit)
            ->get();

        return response()->json($briefings);
    }

    private function regionToCountryCodes(string $region): ?array
    {
        $countryToContinent = config('geo.country_to_continent');

        $codes = array_keys(array_filter(
            $countryToContinent,
            fn(string $continent) => $continent === $region
        ));

        return count($codes) > 0 ? $codes : null;
    }
}
