<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\SeoMeta;
use App\Models\ConflictThread;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ThreadController extends Controller
{
    public function show(ConflictThread $thread): Response
    {
        $thread->load([
            'events' => fn($q) => $q->with('source')->orderBy('occurred_at'),
        ]);

        request()->attributes->set('seo', SeoMeta::make(
            title: $thread->name,
            description: $thread->summary
                ? Str::limit($thread->summary, 155)
                : "Conflict thread: {$thread->name}. Timeline of events and intelligence updates.",
            canonical: url("/thread/{$thread->id}"),
            ogType: 'article',
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Conflicts', 'url' => url('/conflicts')],
                ['name' => $thread->name],
            ],
        ));

        return Inertia::render('Thread/Show', [
            'thread' => $thread,
            'events' => $thread->events,
        ]);
    }
}
