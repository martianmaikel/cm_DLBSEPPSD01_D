<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConflictThread;
use App\Models\CountryIntelligence;
use App\Models\Event;
use App\Services\ThreatLevel\WorldThreatLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MapApiController extends Controller
{
    public function hotzones(Request $request): JsonResponse
    {
        $period = $request->input('period', '7d');
        $hours = match ($period) {
            '30d' => 720,
            '90d' => 2160,
            default => 168,
        };

        $category = $request->input('category');

        $base = Event::recent($hours)->whereNotNull('coordinates');

        $availableCategories = (clone $base)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values()
            ->all();

        $events = (clone $base)
            ->when($category, fn ($q) => $q->byCategory($category))
            ->select('id', 'category', 'severity', 'occurred_at', 'coordinates')
            ->orderByDesc('severity')
            ->orderByDesc('occurred_at')
            ->limit(10000)
            ->get()
            ->map(fn (Event $e) => [
                'coordinates' => $e->coordinates,
                'severity' => (int) $e->severity,
                'category' => $e->category,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ])
            ->filter(fn ($row) => is_array($row['coordinates']) && count($row['coordinates']) === 2)
            ->values();

        return response()->json([
            'period' => $period,
            'category' => $category,
            'count' => $events->count(),
            'available_categories' => $availableCategories,
            'events' => $events->all(),
        ]);
    }

    public function countryHeat(Request $request): JsonResponse
    {
        $timeRange = $request->input('time_range', '24h');
        $hours = match ($timeRange) {
            '48h' => 48,
            '7d' => 168,
            'all' => 8760,
            default => 24,
        };

        $rows = Event::recent($hours)
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as event_count, MAX(severity) as max_severity, AVG(severity) as avg_severity')
            ->groupBy('country')
            ->get();

        $result = $rows->map(fn($row) => [
            'country'       => $row->country,
            'event_count'   => (int) $row->event_count,
            'max_severity'  => (int) $row->max_severity,
            'avg_severity'  => round((float) $row->avg_severity, 1),
            'hotzone_level' => $this->hotzoneLevel((int) $row->max_severity),
        ])->values()->all();

        return response()->json($result);
    }

    public function worldData(): JsonResponse
    {
        $countryToContinent = config('geo.country_to_continent');
        $continentMeta = config('geo.continents');

        $recentEvents = Event::recent(24)
            ->select('country', 'severity')
            ->get();

        $buckets = [];

        foreach ($recentEvents as $event) {
            $continent = $countryToContinent[$event->country] ?? null;
            if ($continent === null) {
                continue;
            }

            if (!isset($buckets[$continent])) {
                $buckets[$continent] = [
                    'continent' => $continentMeta[$continent]['name'] ?? $continent,
                    'slug' => $continent,
                    'event_count' => 0,
                    'max_severity' => 0,
                    'severity_sum' => 0,
                ];
            }

            $buckets[$continent]['event_count']++;
            $buckets[$continent]['severity_sum'] += $event->severity;
            if ($event->severity > $buckets[$continent]['max_severity']) {
                $buckets[$continent]['max_severity'] = $event->severity;
            }
        }

        $result = array_values(array_map(function (array $b): array {
            $avg = $b['event_count'] > 0
                ? round($b['severity_sum'] / $b['event_count'], 2)
                : 0.0;

            return [
                'continent' => $b['continent'],
                'slug' => $b['slug'],
                'event_count' => $b['event_count'],
                'max_severity' => $b['max_severity'],
                'avg_severity' => $avg,
                'hotzone_level' => $this->hotzoneLevel($b['max_severity']),
            ];
        }, $buckets));

        return response()->json($result);
    }

    public function threatLevel(): JsonResponse
    {
        $cached = Cache::get('threat_level:full');

        if ($cached) {
            return response()->json($cached);
        }

        // Fallback: compute score synchronously (no LLM call)
        $service = app(WorldThreatLevelService::class);
        $result = $service->computeScoreOnly();

        return response()->json($result);
    }

    public function countryBrief(string $code): JsonResponse
    {
        $code = strtoupper($code);
        $countryNames = config('geo.country_names', []);

        $intelligence = CountryIntelligence::find($code);

        $topEvents = Event::byCountry($code)
            ->recent(24)
            ->orderByDesc('severity')
            ->orderByDesc('occurred_at')
            ->limit(5)
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'title_de' => $e->title_de,
                'category' => $e->category,
                'severity' => $e->severity,
                'status' => $e->status,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'country_code' => $e->country,
            ]);

        $activeThreads = ConflictThread::open()
            ->topLevel()
            ->whereHas('events', fn ($q) => $q->where('country', $code))
            ->withCount(['events' => fn ($q) => $q->where('country', $code)])
            ->orderByDesc('max_severity')
            ->limit(2)
            ->get()
            ->map(fn (ConflictThread $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'event_count' => $t->events_count,
                'max_severity' => $t->max_severity,
            ]);

        return response()->json([
            'country' => [
                'code' => $code,
                'name' => $countryNames[$code] ?? $code,
            ],
            'intelligence' => $intelligence ? [
                'threat_level' => $intelligence->threat_level,
                'intelligence_briefing_en' => $intelligence->intelligence_briefing_en,
                'intelligence_briefing_de' => $intelligence->intelligence_briefing_de,
                'event_count_24h' => $intelligence->event_count_24h,
                'max_severity' => $intelligence->max_severity,
                'avg_severity' => $intelligence->avg_severity,
                'generated_at' => $intelligence->generated_at?->toIso8601String(),
            ] : null,
            'topEvents' => $topEvents,
            'activeThreads' => $activeThreads,
        ]);
    }

    private function hotzoneLevel(int $maxSeverity): string
    {
        return match (true) {
            $maxSeverity >= 8 => 'critical',
            $maxSeverity >= 5 => 'high',
            $maxSeverity >= 3 => 'medium',
            $maxSeverity > 0  => 'low',
            default           => 'none',
        };
    }
}
