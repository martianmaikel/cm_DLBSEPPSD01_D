<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\SeoMeta;
use Inertia\Inertia;
use Inertia\Response;

class GraphController extends Controller
{
    public function index(): Response
    {
        request()->attributes->set('seo', SeoMeta::make(
            title: 'Graph — Actors, Conflicts, Countries',
            description: 'Interactive relationship graph of actors, organizations, countries and conflicts tracked by ClashMonitor.',
            canonical: url('/graph'),
            ogType: 'website',
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Graph'],
            ],
        ));

        return Inertia::render('Graph/Index');
    }
}
