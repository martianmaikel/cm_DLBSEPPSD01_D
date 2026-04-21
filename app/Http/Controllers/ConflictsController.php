<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\SeoMeta;
use App\Models\ConflictThread;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ConflictsController extends Controller
{
    public function index(): Response
    {
        $conflicts = ConflictThread::topLevel()
            ->open()
            ->with(['children' => fn ($q) => $q->open()->orderByDesc('max_severity')])
            ->orderByDesc('max_severity')
            ->get()
            ->map(fn (ConflictThread $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'summary' => $t->summary,
                'countries' => $t->countries ?? [],
                'categories' => $t->categories ?? [],
                'event_count_24h' => $t->event_count_24h,
                'event_count_total' => $t->event_count_total,
                'max_severity' => $t->max_severity,
                'sub_thread_count' => $t->sub_thread_count,
                'children' => $t->children->map(fn (ConflictThread $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'event_count_total' => $c->event_count_total,
                    'max_severity' => $c->max_severity,
                ]),
            ]);

        $continents = config('geo.continents');
        $countryToContinent = config('geo.country_to_continent');

        // Group conflicts by region based on their countries
        $regionGroups = [];
        foreach ($conflicts as $conflict) {
            $regions = [];
            foreach ($conflict['countries'] as $code) {
                $continent = $countryToContinent[$code] ?? 'other';
                $regions[$continent] = true;
            }
            foreach (array_keys($regions) as $region) {
                $regionGroups[$region][] = $conflict;
            }
        }

        request()->attributes->set('seo', SeoMeta::make(
            title: __('seo.conflicts.title'),
            description: __('seo.conflicts.description', ['count' => $conflicts->count()]),
            canonical: url('/conflicts'),
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Conflicts'],
            ],
            jsonLd: [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => __('seo.conflicts.title'),
                    'description' => __('seo.conflicts.description', ['count' => $conflicts->count()]),
                    'url' => url('/conflicts'),
                    'publisher' => ['@type' => 'Organization', 'name' => 'ClashMonitor'],
                ],
            ],
        ));

        return Inertia::render('Conflicts/Index', [
            'conflicts' => $conflicts,
            'regionGroups' => $regionGroups,
            'continents' => $continents,
        ]);
    }

    public function timeline(string $slug): Response
    {
        $thread = ConflictThread::where('slug', $slug)
            ->with(['children' => fn ($q) => $q->orderByDesc('max_severity')])
            ->firstOrFail();

        $childIds = $thread->children->pluck('id');
        $allThreadIds = $childIds->push($thread->id);

        $events = \App\Models\Event::whereIn('conflict_thread_id', $allThreadIds)
            ->whereNotIn('status', ['pending_classification', 'retracted'])
            ->whereNotNull('occurred_at')
            ->select('id', 'title', 'title_de', 'category', 'subcategory', 'severity', 'confidence', 'status', 'occurred_at', 'country', 'conflict_thread_id')
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (\App\Models\Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'title_de' => $e->title_de,
                'category' => $e->category,
                'subcategory' => $e->subcategory,
                'severity' => (int) $e->severity,
                'confidence' => (int) $e->confidence,
                'status' => $e->status,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'country' => $e->country,
                'thread_id' => $e->conflict_thread_id,
            ]);

        $description = $thread->summary
            ? Str::limit($thread->summary, 155)
            : "Timeline of {$events->count()} events for {$thread->name}. Severity over time, color-coded by category.";

        request()->attributes->set('seo', SeoMeta::make(
            title: "{$thread->name} · Timeline",
            description: $description,
            canonical: url("/conflict/{$thread->slug}/timeline"),
            ogType: 'article',
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Conflicts', 'url' => url('/conflicts')],
                ['name' => $thread->name, 'url' => url("/conflict/{$thread->slug}")],
                ['name' => 'Timeline'],
            ],
        ));

        return Inertia::render('Conflicts/Timeline', [
            'conflict' => [
                'id' => $thread->id,
                'name' => $thread->name,
                'slug' => $thread->slug,
                'summary' => $thread->summary,
                'countries' => $thread->countries ?? [],
                'max_severity' => $thread->max_severity,
                'event_count_total' => $thread->event_count_total,
                'children' => $thread->children->map(fn (ConflictThread $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ]),
            ],
            'events' => $events,
        ]);
    }

    public function show(string $slug): Response
    {
        $thread = ConflictThread::where('slug', $slug)
            ->with(['children' => fn ($q) => $q->open()->orderByDesc('max_severity')])
            ->firstOrFail();

        $childIds = $thread->children->pluck('id');
        $allThreadIds = $childIds->push($thread->id);

        $events = \App\Models\Event::whereIn('conflict_thread_id', $allThreadIds)
            ->whereNotIn('status', ['pending_classification', 'retracted'])
            ->with('source')
            ->orderByDesc('occurred_at')
            ->paginate(50);

        $description = $thread->summary
            ? Str::limit($thread->summary, 155)
            : "Monitoring the {$thread->name} conflict. {$thread->event_count_total} events tracked.";

        request()->attributes->set('seo', SeoMeta::make(
            title: $thread->name,
            description: $description,
            canonical: url("/conflict/{$thread->slug}"),
            ogType: 'article',
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Conflicts', 'url' => url('/conflicts')],
                ['name' => $thread->name],
            ],
            jsonLd: [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => $thread->name,
                    'description' => $description,
                    'url' => url("/conflict/{$thread->slug}"),
                    'numberOfItems' => $thread->event_count_total,
                    'publisher' => ['@type' => 'Organization', 'name' => 'ClashMonitor'],
                ],
            ],
        ));

        return Inertia::render('Conflicts/Show', [
            'conflict' => [
                'id' => $thread->id,
                'name' => $thread->name,
                'slug' => $thread->slug,
                'summary' => $thread->summary,
                'status' => $thread->status,
                'countries' => $thread->countries ?? [],
                'categories' => $thread->categories ?? [],
                'event_count_24h' => $thread->event_count_24h,
                'event_count_total' => $thread->event_count_total,
                'max_severity' => $thread->max_severity,
                'sub_thread_count' => $thread->sub_thread_count,
                'children' => $thread->children->map(fn (ConflictThread $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'summary' => $c->summary,
                    'event_count_total' => $c->event_count_total,
                    'event_count_24h' => $c->event_count_24h,
                    'max_severity' => $c->max_severity,
                    'countries' => $c->countries ?? [],
                    'categories' => $c->categories ?? [],
                ]),
            ],
            'events' => $events,
        ]);
    }
}
