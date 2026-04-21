<?php

namespace App\Http\Controllers;

use App\Models\ConflictThread;
use App\Models\DailyBriefing;
use App\Models\Event;
use App\DataTransferObjects\SeoMeta;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        request()->attributes->set('seo', SeoMeta::make(
            title: __('seo.home.title'),
            description: __('seo.home.description'),
            canonical: url('/'),
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            jsonLd: [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    'name' => 'ClashMonitor',
                    'url' => url('/'),
                    'description' => 'Real-time conflict monitoring and OSINT intelligence platform.',
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => 'ClashMonitor',
                        'url' => url('/'),
                        'logo' => ['@type' => 'ImageObject', 'url' => url('/icon-512.png')],
                    ],
                ],
            ],
        ));


        $events = Event::recent(24)
            ->with('source')
            ->select([
                'id', 'title', 'title_de', 'summary', 'summary_de', 'severity', 'severity_factors',
                'confidence', 'status', 'category', 'country', 'region',
                'coordinates', 'occurred_at', 'source_id', 'conflict_thread_id',
                'corroboration_count', 'entities_json',
            ])
            ->orderByDesc('occurred_at')
            ->limit(500)
            ->get()
            ->map(fn(Event $e) => [
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
                'region' => $e->region,
                'coordinates' => $e->coordinates,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'source_name' => $e->source?->name,
                'source_url' => $e->source_url ?: $e->source?->url,
                'source_reliability' => $e->source?->reliability_score,
                'entities_json' => $e->entities_json,
                'conflict_thread_id' => $e->conflict_thread_id,
                'corroboration_count' => $e->corroboration_count,
            ]);

        $threads = ConflictThread::withCount('events')
            ->withMax('events', 'severity')
            ->withMax('events', 'occurred_at')
            ->whereHas('events')
            ->orderByDesc('events_max_occurred_at')
            ->get()
            ->map(fn(ConflictThread $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'summary' => $t->summary,
                'status' => $t->status,
                'event_count' => $t->events_count,
                'max_severity' => (int) $t->events_max_severity,
                'latest_event_at' => $t->events_max_occurred_at,
            ]);

        $briefing = DailyBriefing::latest()->first();

        return Inertia::render('Dashboard/Index', [
            'events' => $events,
            'threads' => $threads,
            'briefing' => $briefing,
        ]);
    }
}
