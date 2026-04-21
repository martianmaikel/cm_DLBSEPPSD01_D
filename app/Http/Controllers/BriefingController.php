<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\SeoMeta;
use App\Models\DailyBriefing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BriefingController extends Controller
{
    public function show(?string $date = null): Response|RedirectResponse
    {
        if (! $date) {
            $latest = DailyBriefing::latest()->first();

            if (! $latest) {
                abort(404);
            }

            return redirect("/briefing/{$latest->briefing_date->format('Y-m-d')}");
        }

        $briefing = DailyBriefing::forDate(now()->parse($date))->first();

        if (! $briefing) {
            abort(404);
        }

        // Navigation: previous and next briefing dates
        $previous = DailyBriefing::where('briefing_date', '<', $briefing->briefing_date)
            ->latest()
            ->value('briefing_date');

        $next = DailyBriefing::where('briefing_date', '>', $briefing->briefing_date)
            ->orderBy('briefing_date')
            ->value('briefing_date');

        $locale = app()->getLocale();
        $formattedDate = $locale === 'de'
            ? $briefing->briefing_date->locale('de')->isoFormat('D. MMMM YYYY')
            : $briefing->briefing_date->format('M j, Y');
        $summary = $locale === 'de'
            ? ($briefing->summary_de ?? $briefing->summary_en ?? '')
            : ($briefing->summary_en ?? $briefing->summary ?? '');

        request()->attributes->set('seo', SeoMeta::make(
            title: __('seo.briefing.title', ['date' => $formattedDate]),
            description: $summary ?: __('seo.briefing.description', ['date' => $formattedDate]),
            canonical: url("/briefing/{$briefing->briefing_date->format('Y-m-d')}"),
            ogType: 'article',
            ogImage: url("/og/briefing/{$briefing->briefing_date->format('Y-m-d')}"),
            twitterCard: 'summary_large_image',
            publishedAt: $briefing->generated_at?->toIso8601String() ?? $briefing->created_at->toIso8601String(),
            modifiedAt: $briefing->updated_at->toIso8601String(),
            prevUrl: $previous ? url("/briefing/{$previous->format('Y-m-d')}") : null,
            nextUrl: $next ? url("/briefing/{$next->format('Y-m-d')}") : null,
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Briefings', 'url' => url('/briefing')],
                ['name' => $formattedDate],
            ],
            jsonLd: [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'ReportageNewsArticle',
                    'headline' => __('seo.briefing.title', ['date' => $formattedDate]),
                    'description' => Str::limit($summary, 200),
                    'datePublished' => $briefing->generated_at?->toIso8601String() ?? $briefing->created_at->toIso8601String(),
                    'dateModified' => $briefing->updated_at->toIso8601String(),
                    'author' => ['@type' => 'Organization', 'name' => 'ClashMonitor'],
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => 'ClashMonitor',
                        'logo' => ['@type' => 'ImageObject', 'url' => url('/icon-512.png')],
                    ],
                    'mainEntityOfPage' => url("/briefing/{$briefing->briefing_date->format('Y-m-d')}"),
                    'image' => url('/images/og-banner.jpg'),
                ],
            ],
        ));

        return Inertia::render('Briefing/Show', [
            'briefing' => $briefing,
            'previousDate' => $previous?->format('Y-m-d'),
            'nextDate' => $next?->format('Y-m-d'),
        ]);
    }
}
