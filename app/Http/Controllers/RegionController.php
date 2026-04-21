<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\SeoMeta;
use App\Models\CountryIntelligence;
use App\Models\ConflictThread;
use App\Models\Event;
use Inertia\Inertia;
use Inertia\Response;

class RegionController extends Controller
{
    public function show(string $slug): Response
    {
        $continents = config('geo.continents');
        $countryToContinent = config('geo.country_to_continent');
        $countryNames = config('geo.country_names', []);

        // Validate region
        if (! isset($continents[$slug])) {
            abort(404, 'Region not found');
        }

        $regionMeta = $continents[$slug];

        // Get all country codes in this region
        $countryCodes = array_keys(array_filter(
            $countryToContinent,
            fn (string $continent) => $continent === $slug
        ));

        // Country intelligence for this region
        $countryIntel = CountryIntelligence::whereIn('country_code', $countryCodes)
            ->orderByDesc('threat_level')
            ->get()
            ->map(fn (CountryIntelligence $ci) => [
                'code' => $ci->country_code,
                'name' => $ci->country_name,
                'threat_level' => $ci->threat_level,
                'event_count_24h' => $ci->event_count_24h,
                'event_count_total' => $ci->event_count_total,
                'max_severity' => $ci->max_severity,
                'category_breakdown' => $ci->category_breakdown,
            ]);

        // Active conflicts in this region
        $activeConflicts = ConflictThread::topLevel()
            ->open()
            ->get()
            ->filter(function (ConflictThread $t) use ($countryCodes) {
                $threadCountries = $t->countries ?? [];

                return count(array_intersect($threadCountries, $countryCodes)) > 0;
            })
            ->map(fn (ConflictThread $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'max_severity' => $t->max_severity,
                'event_count_total' => $t->event_count_total,
                'event_count_24h' => $t->event_count_24h,
                'countries' => $t->countries,
                'categories' => $t->categories,
            ])
            ->values();

        // Aggregate stats
        $regionStats = Event::whereIn('country', $countryCodes)
            ->whereNotIn('status', ['pending_classification', 'retracted'])
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as last_24h,
                MAX(severity) as max_sev,
                AVG(severity) as avg_sev
            ", [now()->subHours(24)])
            ->first();

        request()->attributes->set('seo', SeoMeta::make(
            title: __('seo.region.title', ['name' => $regionMeta['name']]),
            description: __('seo.region.description', [
                'name' => $regionMeta['name'],
                'events' => $regionStats->last_24h ?? 0,
                'countries' => count($countryCodes),
            ]),
            canonical: url("/region/{$slug}"),
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Conflicts', 'url' => url('/conflicts')],
                ['name' => $regionMeta['name']],
            ],
        ));

        return Inertia::render('Region/Show', [
            'region' => [
                'slug' => $slug,
                'name' => $regionMeta['name'],
                'country_count' => count($countryCodes),
                'event_count_24h' => $regionStats->last_24h ?? 0,
                'event_count_total' => $regionStats->total ?? 0,
                'max_severity' => $regionStats->max_sev ?? 0,
                'avg_severity' => round($regionStats->avg_sev ?? 0, 1),
            ],
            'countries' => $countryIntel,
            'activeConflicts' => $activeConflicts,
        ]);
    }
}
