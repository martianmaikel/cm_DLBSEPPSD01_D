<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\SeoMeta;
use App\Models\ConflictThread;
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class DigestController extends Controller
{
    public function latest(): Response
    {
        $now = CarbonImmutable::now();
        // Show last completed week by default (current week is still in progress)
        $target = $now->subWeek();
        return $this->renderWeek($target->isoWeekYear, $target->isoWeek);
    }

    public function show(string $week): Response
    {
        if (!preg_match('/^(\d{4})-W(\d{1,2})$/', $week, $m)) {
            abort(404);
        }
        return $this->renderWeek((int) $m[1], (int) $m[2]);
    }

    private function renderWeek(int $year, int $week): Response
    {
        if ($week < 1 || $week > 53) {
            abort(404);
        }

        try {
            $start = CarbonImmutable::create($year, 1, 1)->setISODate($year, $week)->startOfDay();
        } catch (\Throwable $e) {
            abort(404);
        }
        $end = $start->addDays(7)->subSecond();

        if ($start->isFuture()) {
            abort(404);
        }

        $prevStart = $start->subWeek();
        $prevEnd = $start->subSecond();

        $baseFilter = function (Builder $q) use ($start, $end) {
            $q->where(function (Builder $inner) use ($start, $end) {
                $inner->whereBetween('occurred_at', [$start, $end])
                      ->orWhere(function (Builder $alt) use ($start, $end) {
                          $alt->whereNull('occurred_at')
                              ->whereBetween('created_at', [$start, $end]);
                      });
            })
            ->whereNotIn('status', ['pending_classification', 'retracted']);
        };

        $totalEvents = Event::query()->tap($baseFilter)->count();

        $severityBreakdown = [
            'low' => Event::query()->tap($baseFilter)->whereBetween('severity', [1, 3])->count(),
            'medium' => Event::query()->tap($baseFilter)->whereBetween('severity', [4, 6])->count(),
            'high' => Event::query()->tap($baseFilter)->whereBetween('severity', [7, 10])->count(),
        ];

        $confirmedCount = Event::query()->tap($baseFilter)->where('status', 'confirmed')->count();
        $corroboratedCount = Event::query()->tap($baseFilter)->where('status', 'corroborated')->count();

        $countriesAffected = Event::query()->tap($baseFilter)
            ->whereNotNull('country')
            ->distinct()
            ->pluck('country')
            ->count();

        $topEvents = Event::query()->tap($baseFilter)
            ->with('conflictThread:id,name,slug')
            ->orderByDesc('severity')
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'title_de' => $e->title_de,
                'summary' => $e->summary,
                'summary_de' => $e->summary_de,
                'category' => $e->category,
                'severity' => (int) $e->severity,
                'confidence' => (int) $e->confidence,
                'status' => $e->status,
                'country' => $e->country,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'slug' => $e->slug,
                'thread' => $e->conflictThread ? [
                    'name' => $e->conflictThread->name,
                    'slug' => $e->conflictThread->slug,
                ] : null,
            ])
            ->all();

        $threadCountsRaw = Event::query()->tap($baseFilter)
            ->whereNotNull('conflict_thread_id')
            ->selectRaw('conflict_thread_id, COUNT(*) as event_count, MAX(severity) as max_severity')
            ->groupBy('conflict_thread_id')
            ->orderByDesc('event_count')
            ->limit(10)
            ->get();

        $threadIds = $threadCountsRaw->pluck('conflict_thread_id')->all();
        $threadsById = ConflictThread::whereIn('id', $threadIds)->get()->keyBy('id');

        $prevThreadCounts = Event::query()
            ->whereBetween('occurred_at', [$prevStart, $prevEnd])
            ->whereIn('conflict_thread_id', $threadIds)
            ->selectRaw('conflict_thread_id, COUNT(*) as c')
            ->groupBy('conflict_thread_id')
            ->pluck('c', 'conflict_thread_id');

        $threadScoreboard = $threadCountsRaw->map(function ($row) use ($threadsById, $prevThreadCounts) {
            $t = $threadsById->get($row->conflict_thread_id);
            $prev = (int) ($prevThreadCounts[$row->conflict_thread_id] ?? 0);
            $curr = (int) $row->event_count;
            return [
                'id' => $t?->id,
                'name' => $t?->name ?? 'Unknown',
                'slug' => $t?->slug,
                'event_count' => $curr,
                'max_severity' => (int) $row->max_severity,
                'delta' => $curr - $prev,
                'countries' => $t?->countries ?? [],
            ];
        })->values()->all();

        $categoryBreakdown = Event::query()->tap($baseFilter)
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as c')
            ->groupBy('category')
            ->orderByDesc('c')
            ->get()
            ->map(fn ($r) => ['category' => $r->category, 'count' => (int) $r->c])
            ->all();

        $countryNames = config('geo.country_names', []);
        $countryRanking = Event::query()->tap($baseFilter)
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as c, MAX(severity) as max_sev')
            ->groupBy('country')
            ->orderByDesc('c')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'code' => $r->country,
                'name' => $countryNames[$r->country] ?? $r->country,
                'count' => (int) $r->c,
                'max_severity' => (int) $r->max_sev,
            ])
            ->all();

        $newThreads = ConflictThread::whereBetween('created_at', [$start, $end])
            ->orderByDesc('max_severity')
            ->limit(10)
            ->get()
            ->map(fn (ConflictThread $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'summary' => $t->summary,
                'event_count_total' => $t->event_count_total,
                'max_severity' => $t->max_severity,
            ])
            ->all();

        $hotzoneEvents = Event::query()->tap($baseFilter)
            ->whereNotNull('coordinates')
            ->select('coordinates', 'severity', 'category')
            ->orderByDesc('severity')
            ->limit(500)
            ->get()
            ->map(fn (Event $e) => [
                'coordinates' => $e->coordinates,
                'severity' => (int) $e->severity,
                'category' => $e->category,
            ])
            ->filter(fn ($r) => is_array($r['coordinates']) && count($r['coordinates']) === 2)
            ->values()
            ->all();

        $weekLabel = sprintf('%d-W%02d', $year, $week);
        $now = CarbonImmutable::now();
        $thisWeekStart = $now->startOfWeek();
        $hasNext = $end->lt($thisWeekStart);
        $nextStart = $start->addWeek();

        request()->attributes->set('seo', SeoMeta::make(
            title: "Weekly Digest · {$weekLabel}",
            description: "Weekly conflict digest for " . $start->format('M j') . "–" . $end->format('M j, Y') . ": {$totalEvents} events tracked in {$countriesAffected} countries, {$confirmedCount} confirmed.",
            canonical: url("/digest/{$weekLabel}"),
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Digest', 'url' => url('/digest')],
                ['name' => $weekLabel],
            ],
        ));

        return Inertia::render('Digest/Show', [
            'week' => [
                'label' => $weekLabel,
                'year' => $year,
                'week' => $week,
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'prev' => sprintf('%d-W%02d', $prevStart->isoWeekYear, $prevStart->isoWeek),
                'next' => $hasNext ? sprintf('%d-W%02d', $nextStart->isoWeekYear, $nextStart->isoWeek) : null,
            ],
            'statistics' => [
                'total_events' => $totalEvents,
                'severity_breakdown' => $severityBreakdown,
                'confirmed_count' => $confirmedCount,
                'corroborated_count' => $corroboratedCount,
                'countries_affected' => $countriesAffected,
                'new_threads_count' => count($newThreads),
            ],
            'topEvents' => $topEvents,
            'threadScoreboard' => $threadScoreboard,
            'categoryBreakdown' => $categoryBreakdown,
            'countryRanking' => $countryRanking,
            'newThreads' => $newThreads,
            'hotzoneEvents' => $hotzoneEvents,
        ]);
    }
}
