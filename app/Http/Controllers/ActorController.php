<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\SeoMeta;
use App\Models\Actor;
use App\Models\EntityExtraction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ActorController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Actor::query()->where('enrichment_status', 'enriched');

        if ($request->filled('type')) {
            $type = $request->string('type')->toString();
            if (in_array($type, ['person', 'organization'], true)) {
                $query->where('actor_type', $type);
            }
        }

        if ($request->filled('country')) {
            $query->where('country', strtoupper($request->string('country')->toString()));
        }

        if ($request->filled('search')) {
            $needle = '%' . mb_strtolower($request->string('search')->toString()) . '%';
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(canonical_name) LIKE ?', [$needle])
                  ->orWhereRaw('LOWER(COALESCE(full_name, \'\')) LIKE ?', [$needle])
                  ->orWhereRaw('EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(aliases, \'[]\'::jsonb)) a WHERE LOWER(a) LIKE ?)', [$needle]);
            });
        }

        $actors = $query
            ->orderByDesc('event_count')
            ->orderByDesc('last_mentioned_at')
            ->paginate(36)
            ->withQueryString();

        request()->attributes->set('seo', SeoMeta::make(
            title: 'Actors — Conflict-relevant persons & organizations',
            description: 'Directory of conflict-relevant persons and organizations tracked by ClashMonitor, enriched from events and public sources.',
            canonical: url('/actors'),
            ogType: 'website',
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Actors'],
            ],
        ));

        return Inertia::render('Actors/Index', [
            'actors' => $actors,
            'filters' => $request->only(['type', 'country', 'search']),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $actor = Actor::where('slug', $slug)
            ->where('enrichment_status', 'enriched')
            ->firstOrFail();

        $events = EntityExtraction::where('actor_id', $actor->id)
            ->with(['event' => fn ($q) => $q->with('source')->select(
                'id', 'slug', 'title', 'title_de', 'summary', 'summary_de',
                'occurred_at', 'country', 'region', 'category', 'subcategory',
                'severity', 'status', 'source_id'
            )])
            ->get()
            ->pluck('event')
            ->filter()
            ->unique('id')
            ->sortByDesc(fn ($e) => optional($e->occurred_at)->getTimestamp() ?? 0)
            ->take(50)
            ->values();

        $actor->loadMissing(['affiliation:id,slug,canonical_name,actor_type', 'parent:id,slug,canonical_name,actor_type']);

        $description = $actor->summary_short
            ?: ($actor->relevance_summary
                ? Str::limit($actor->relevance_summary, 155)
                : "Dossier on {$actor->canonical_name}, a conflict-relevant " . $actor->actor_type . ' tracked by ClashMonitor.');

        request()->attributes->set('seo', SeoMeta::make(
            title: $actor->canonical_name,
            description: $description,
            canonical: url("/actor/{$actor->slug}"),
            ogType: $actor->actor_type === 'person' ? 'profile' : 'website',
            ogImage: url("/og/actor/{$actor->id}"),
            twitterCard: 'summary_large_image',
            modifiedAt: $actor->updated_at->toIso8601String(),
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Actors', 'url' => url('/actors')],
                ['name' => $actor->canonical_name],
            ],
            jsonLd: [
                $this->buildJsonLd($actor),
            ],
        ));

        return Inertia::render('Actors/Show', [
            'actor' => $actor,
            'events' => $events,
        ]);
    }

    private function buildJsonLd(Actor $actor): array
    {
        $base = [
            '@context' => 'https://schema.org',
            '@type' => $actor->actor_type === 'person' ? 'Person' : 'Organization',
            'name' => $actor->canonical_name,
            'url' => url("/actor/{$actor->slug}"),
            'description' => $actor->summary_long ?? $actor->summary_short,
            'image' => $actor->image_url ?: url("/og/actor/{$actor->id}"),
        ];

        if ($actor->aliases) {
            $base['alternateName'] = array_values($actor->aliases);
        }

        if ($actor->actor_type === 'person') {
            if ($actor->role_title) {
                $base['jobTitle'] = $actor->role_title;
            }
            if ($actor->nationality) {
                $base['nationality'] = $actor->nationality;
            }
            if ($actor->birth_year) {
                $base['birthDate'] = (string) $actor->birth_year;
            }
            if ($actor->death_year) {
                $base['deathDate'] = (string) $actor->death_year;
            }
        } else {
            if ($actor->founded_year) {
                $base['foundingDate'] = (string) $actor->founded_year;
            }
            if ($actor->dissolved_year) {
                $base['dissolutionDate'] = (string) $actor->dissolved_year;
            }
        }

        return array_filter($base);
    }
}
