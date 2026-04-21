<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\SeoMeta;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function show(Event $event, ?string $slug = null): Response|RedirectResponse
    {
        // 301 redirect to canonical slug URL if slug is missing or wrong
        if ($event->slug && $slug !== $event->slug) {
            return redirect("/event/{$event->id}-{$event->slug}", 301);
        }

        $event->load([
            'source.sourceFamily',
            'conflictThread',
            'corroborationLinksAsA.eventB.source',
            'corroborationLinksAsB.eventA.source',
        ]);

        $locale = app()->getLocale();
        $seoTitle = ($locale === 'de' && $event->title_de) ? $event->title_de : $event->title;
        $seoDescription = ($locale === 'de' && $event->summary_de) ? $event->summary_de : ($event->summary ?? $event->title);

        request()->attributes->set('seo', SeoMeta::make(
            title: $seoTitle,
            description: $seoDescription,
            canonical: url("/event/{$event->id}" . ($event->slug ? "-{$event->slug}" : '')),
            ogType: 'article',
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            publishedAt: $event->occurred_at?->toIso8601String(),
            modifiedAt: $event->updated_at->toIso8601String(),
            breadcrumbs: array_filter([
                ['name' => 'ClashMonitor', 'url' => url('/')],
                $event->country ? ['name' => $event->country, 'url' => url("/country/{$event->country}")] : null,
                ['name' => Str::limit($event->title, 60)],
            ]),
            jsonLd: [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'NewsArticle',
                    'headline' => Str::limit($event->title, 110),
                    'description' => Str::limit($event->summary ?? $event->title, 200),
                    'datePublished' => $event->occurred_at?->toIso8601String(),
                    'dateModified' => $event->updated_at->toIso8601String(),
                    'author' => ['@type' => 'Organization', 'name' => 'ClashMonitor'],
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => 'ClashMonitor',
                        'logo' => ['@type' => 'ImageObject', 'url' => url('/icon-512.png')],
                    ],
                    'mainEntityOfPage' => url("/event/{$event->id}" . ($event->slug ? "-{$event->slug}" : '')),
                    'articleSection' => $event->category,
                    'image' => url('/images/og-banner.jpg'),
                ],
            ],
        ));

        $corroborationChain = collect()
            ->merge($event->corroborationLinksAsA->map(fn($link) => [
                'event' => $link->eventB,
                'similarity_score' => $link->similarity_score,
                'match_method' => $link->match_method,
                'cross_family' => $link->cross_family,
            ]))
            ->merge($event->corroborationLinksAsB->map(fn($link) => [
                'event' => $link->eventA,
                'similarity_score' => $link->similarity_score,
                'match_method' => $link->match_method,
                'cross_family' => $link->cross_family,
            ]))
            ->values();

        return Inertia::render('Event/Show', [
            'event' => $event,
            'corroboration_chain' => $corroborationChain,
        ]);
    }
}
