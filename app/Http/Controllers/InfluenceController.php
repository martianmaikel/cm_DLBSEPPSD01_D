<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\SeoMeta;
use Inertia\Inertia;
use Inertia\Response;

class InfluenceController extends Controller
{
    public function index(): Response
    {
        request()->attributes->set('seo', SeoMeta::make(
            title: 'Actor Influence — Network Analytics',
            description: 'Ranking of conflict actors by graph centrality (degree, betweenness, PageRank) across the ClashMonitor relationship network.',
            canonical: url('/influence'),
            ogType: 'website',
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Influence'],
            ],
        ));

        return Inertia::render('Influence/Dashboard');
    }

    public function compare(): Response
    {
        request()->attributes->set('seo', SeoMeta::make(
            title: 'Compare Actor Influence',
            description: 'Side-by-side comparison of conflict actors across degree, betweenness and PageRank centrality.',
            canonical: url('/influence/compare'),
            ogType: 'website',
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Influence', 'url' => url('/influence')],
                ['name' => 'Compare'],
            ],
        ));

        return Inertia::render('Influence/Compare');
    }
}
