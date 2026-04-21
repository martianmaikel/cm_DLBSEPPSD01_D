<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\SeoMeta;
use App\Models\ConflictThread;
use App\Models\CountryIntelligence;
use App\Models\EntityExtraction;
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    public function hotzones(Request $request): Response
    {
        $period = $request->input('period', '7d');
        $category = $request->input('category');

        $periodLabel = match ($period) {
            '30d' => 'Last 30 Days',
            '90d' => 'Last 90 Days',
            default => 'Last 7 Days',
        };

        request()->attributes->set('seo', SeoMeta::make(
            title: "Global Conflict Hotzones — {$periodLabel}",
            description: 'Interactive heatmap of global conflict intensity from verified OSINT reports, severity-weighted and geolocated in real time.',
            canonical: url('/map/hotzones'),
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Hotzones'],
            ],
        ));

        return Inertia::render('Map/Hotzones', [
            'period' => in_array($period, ['7d', '30d', '90d'], true) ? $period : '7d',
            'category' => $category,
        ]);
    }

    public function dossier(string $code): Response
    {
        $code = strtoupper($code);

        $countryNames = config('geo.country_names', []);
        $countryToContinent = config('geo.country_to_continent');
        $continentMeta = config('geo.continents');
        $continentSlug = $countryToContinent[$code] ?? null;
        $countryName = $countryNames[$code] ?? $code;

        $intelligence = CountryIntelligence::find($code);

        $now = CarbonImmutable::now();
        $d7 = $now->subDays(7);
        $d30 = $now->subDays(30);

        $baseQuery = Event::byCountry($code)
            ->whereNotIn('status', ['pending_classification', 'retracted']);

        $eventCount7d = (clone $baseQuery)
            ->where(function ($q) use ($d7) {
                $q->where('occurred_at', '>=', $d7)
                  ->orWhere(function ($q2) use ($d7) {
                      $q2->whereNull('occurred_at')->where('created_at', '>=', $d7);
                  });
            })
            ->count();

        $eventCount30d = (clone $baseQuery)
            ->where(function ($q) use ($d30) {
                $q->where('occurred_at', '>=', $d30)
                  ->orWhere(function ($q2) use ($d30) {
                      $q2->whereNull('occurred_at')->where('created_at', '>=', $d30);
                  });
            })
            ->count();

        // Severity trend — one data point per day for last 30 days
        $dailyRows = (clone $baseQuery)
            ->where(function ($q) use ($d30) {
                $q->where('occurred_at', '>=', $d30)
                  ->orWhere(function ($q2) use ($d30) {
                      $q2->whereNull('occurred_at')->where('created_at', '>=', $d30);
                  });
            })
            ->selectRaw("DATE(COALESCE(occurred_at, created_at)) as day, COUNT(*) as c, AVG(severity) as avg_sev, MAX(severity) as max_sev")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $severityTrend = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = $now->subDays($i)->format('Y-m-d');
            $row = $dailyRows->get($day);
            $severityTrend[] = [
                'date' => $day,
                'count' => $row ? (int) $row->c : 0,
                'avg_severity' => $row ? round((float) $row->avg_sev, 2) : 0,
                'max_severity' => $row ? (int) $row->max_sev : 0,
            ];
        }

        // Top 3 active threads
        $activeThreads = ConflictThread::open()
            ->whereHas('events', fn ($q) => $q->where('country', $code))
            ->withCount(['events' => fn ($q) => $q->where('country', $code)])
            ->orderByDesc('events_count')
            ->orderByDesc('max_severity')
            ->limit(3)
            ->get()
            ->map(fn (ConflictThread $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'event_count' => $t->events_count,
                'max_severity' => (int) $t->max_severity,
                'summary' => $t->summary,
            ]);

        // Dominant entities over last 30 days (for this country)
        $topEntities = EntityExtraction::query()
            ->join('events', 'entity_extractions.event_id', '=', 'events.id')
            ->where('events.country', $code)
            ->where(function ($q) use ($d30) {
                $q->where('events.occurred_at', '>=', $d30)
                  ->orWhere(function ($q2) use ($d30) {
                      $q2->whereNull('events.occurred_at')->where('events.created_at', '>=', $d30);
                  });
            })
            ->selectRaw('entity_extractions.name, entity_extractions.type, COUNT(*) as mentions')
            ->groupBy('entity_extractions.name', 'entity_extractions.type')
            ->orderByDesc('mentions')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'type' => $r->type,
                'mentions' => (int) $r->mentions,
            ])
            ->all();

        // Top categories for last 30d if intelligence.category_breakdown is stale
        $topCategories = (clone $baseQuery)
            ->where(function ($q) use ($d30) {
                $q->where('occurred_at', '>=', $d30)
                  ->orWhere(function ($q2) use ($d30) {
                      $q2->whereNull('occurred_at')->where('created_at', '>=', $d30);
                  });
            })
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as c')
            ->groupBy('category')
            ->orderByDesc('c')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['category' => $r->category, 'count' => (int) $r->c])
            ->all();

        $locale = app()->getLocale();
        $briefing = $intelligence
            ? ($locale === 'de' ? $intelligence->intelligence_briefing_de : $intelligence->intelligence_briefing_en)
            : null;

        request()->attributes->set('seo', SeoMeta::make(
            title: "{$countryName} · Intelligence Dossier",
            description: $briefing
                ? Str::limit($briefing, 155)
                : "Intelligence dossier for {$countryName}: threat level, active conflicts, recent events, and dominant actors.",
            canonical: url("/country/{$code}/dossier"),
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            breadcrumbs: array_filter([
                ['name' => 'ClashMonitor', 'url' => url('/')],
                $continentSlug ? ['name' => $continentMeta[$continentSlug]['name'] ?? $continentSlug, 'url' => url("/region/{$continentSlug}")] : null,
                ['name' => $countryName, 'url' => url("/country/{$code}")],
                ['name' => 'Dossier'],
            ]),
        ));

        return Inertia::render('Country/Dossier', [
            'country' => [
                'code' => $code,
                'name' => $countryName,
                'continent_name' => $continentSlug ? ($continentMeta[$continentSlug]['name'] ?? null) : null,
                'continent_slug' => $continentSlug,
            ],
            'intelligence' => $intelligence ? [
                'threat_level' => $intelligence->threat_level,
                'briefing_en' => $intelligence->intelligence_briefing_en,
                'briefing_de' => $intelligence->intelligence_briefing_de,
                'event_count_24h' => $intelligence->event_count_24h,
                'event_count_total' => $intelligence->event_count_total,
                'max_severity' => $intelligence->max_severity,
                'avg_severity' => $intelligence->avg_severity,
                'generated_at' => $intelligence->generated_at?->toIso8601String(),
            ] : null,
            'stats' => [
                'event_count_7d' => $eventCount7d,
                'event_count_30d' => $eventCount30d,
            ],
            'severityTrend' => $severityTrend,
            'topCategories' => $topCategories,
            'topEntities' => $topEntities,
            'activeThreads' => $activeThreads,
        ]);
    }

    public function country(string $code): Response
    {
        $code = strtoupper($code);

        $countryNames = config('geo.country_names', []);
        $countryToContinent = config('geo.country_to_continent');
        $continentMeta = config('geo.continents');
        $continentSlug = $countryToContinent[$code] ?? null;

        $events = Event::byCountry($code)
            ->recent(24)
            ->with('source')
            ->orderByDesc('occurred_at')
            ->paginate(25);

        $coordinates = Event::byCountry($code)
            ->recent(24)
            ->whereNotNull('coordinates')
            ->select('id', 'title', 'title_de', 'severity', 'status', 'coordinates')
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'title_de' => $e->title_de,
                'severity' => $e->severity,
                'status' => $e->status,
                'coordinates' => $e->coordinates,
            ]);

        // Load country intelligence data
        $intelligence = CountryIntelligence::find($code);

        // Get active conflict threads for this country
        $activeThreads = ConflictThread::open()
            ->topLevel()
            ->whereHas('events', fn ($q) => $q->where('country', $code))
            ->withCount(['events' => fn ($q) => $q->where('country', $code)])
            ->get()
            ->map(fn (ConflictThread $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'event_count' => $t->events_count,
                'max_severity' => $t->max_severity,
            ]);

        $countryName = $countryNames[$code] ?? $code;

        $locale = app()->getLocale();
        $seoDescriptionLocalized = $intelligence
            ? \Illuminate\Support\Str::limit(
                ($locale === 'de' ? $intelligence->intelligence_briefing_de : $intelligence->intelligence_briefing_en) ?? '', 155
            )
            : null;

        request()->attributes->set('seo', SeoMeta::make(
            title: __('seo.country.title', ['name' => $countryName]),
            description: $seoDescriptionLocalized ?: __('seo.country.description', ['name' => $countryName]),
            canonical: url("/country/{$code}"),
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            prevUrl: $events->previousPageUrl(),
            nextUrl: $events->nextPageUrl(),
            breadcrumbs: array_filter([
                ['name' => 'ClashMonitor', 'url' => url('/')],
                $continentSlug ? ['name' => $continentMeta[$continentSlug]['name'] ?? $continentSlug, 'url' => url("/region/{$continentSlug}")] : null,
                ['name' => $countryName],
            ]),
        ));

        return Inertia::render('Map/Country', [
            'country' => [
                'code' => $code,
                'name' => $countryNames[$code] ?? $code,
                'continent_name' => $continentSlug ? ($continentMeta[$continentSlug]['name'] ?? null) : null,
                'continent_slug' => $continentSlug,
            ],
            'events' => $events,
            'coordinates' => $coordinates,
            'intelligence' => $intelligence ? [
                'threat_level' => $intelligence->threat_level,
                'intelligence_briefing_en' => $intelligence->intelligence_briefing_en,
                'intelligence_briefing_de' => $intelligence->intelligence_briefing_de,
                'event_count_24h' => $intelligence->event_count_24h,
                'event_count_total' => $intelligence->event_count_total,
                'max_severity' => $intelligence->max_severity,
                'avg_severity' => $intelligence->avg_severity,
                'category_breakdown' => $intelligence->category_breakdown,
                'generated_at' => $intelligence->generated_at?->toIso8601String(),
            ] : null,
            'activeThreads' => $activeThreads,
        ]);
    }
}
