<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\EnrichActorJob;
use App\Jobs\PromoteActorCandidatesJob;
use App\Models\Actor;
use App\Models\ActorCandidate;
use App\Models\EntityExtraction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminActorController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Actor::query();

        if ($request->filled('type')) {
            $query->where('actor_type', $request->string('type')->toString());
        }

        if ($request->filled('country')) {
            $query->where('country', strtoupper($request->string('country')->toString()));
        }

        if ($request->filled('enrichment_status')) {
            $query->where('enrichment_status', $request->string('enrichment_status')->toString());
        }

        if ($request->filled('search')) {
            $needle = '%' . mb_strtolower($request->string('search')->toString()) . '%';
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(canonical_name) LIKE ?', [$needle])
                  ->orWhereRaw('LOWER(COALESCE(full_name, \'\')) LIKE ?', [$needle])
                  ->orWhereRaw('EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(aliases, \'[]\'::jsonb)) a WHERE LOWER(a) LIKE ?)', [$needle]);
            });
        }

        if ($request->filled('min_event_count')) {
            $query->where('event_count', '>=', (int) $request->input('min_event_count'));
        }

        $actors = $query->orderByDesc('event_count')
            ->orderByDesc('last_mentioned_at')
            ->paginate(50)
            ->withQueryString();

        $candidatesCount = ActorCandidate::where('blocked', false)->count();

        return Inertia::render('Admin/Actors/Index', [
            'actors' => $actors,
            'filters' => $request->only(['type', 'country', 'enrichment_status', 'search', 'min_event_count']),
            'candidatesCount' => $candidatesCount,
            'promotionThreshold' => (int) config('actors.promotion_threshold', 3),
            'enrichmentMode' => (string) config('actors.enrichment_mode', 'llm_knowledge'),
        ]);
    }

    public function show(Actor $actor): Response
    {
        $actor->load(['affiliation', 'parent']);

        $events = EntityExtraction::where('actor_id', $actor->id)
            ->with(['event' => fn ($q) => $q->with('source')->select(
                'id', 'slug', 'title', 'title_de', 'summary', 'occurred_at', 'country',
                'region', 'category', 'subcategory', 'severity', 'status', 'source_id'
            )])
            ->get()
            ->pluck('event')
            ->filter()
            ->unique('id')
            ->sortByDesc(fn ($e) => optional($e->occurred_at)->getTimestamp() ?? 0)
            ->values();

        return Inertia::render('Admin/Actors/Show', [
            'actor' => $actor,
            'events' => $events,
        ]);
    }

    public function update(Request $request, Actor $actor): RedirectResponse
    {
        $validated = $request->validate([
            'canonical_name' => ['sometimes', 'required', 'string', 'max:255'],
            'actor_type' => ['sometimes', 'required', 'in:person,organization'],
            'aliases' => ['sometimes', 'nullable', 'array'],
            'aliases.*' => ['string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'summary_short' => ['sometimes', 'nullable', 'string'],
            'summary_long' => ['sometimes', 'nullable', 'string'],
            'relevance_summary' => ['sometimes', 'nullable', 'string'],
            'categories' => ['sometimes', 'nullable', 'array'],
            'categories.*' => ['string'],
            'status' => ['sometimes', 'required', 'in:active,inactive,deceased,dissolved,unknown'],
            'confidence' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'full_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'birth_year' => ['sometimes', 'nullable', 'integer', 'min:1800', 'max:2100'],
            'death_year' => ['sometimes', 'nullable', 'integer', 'min:1800', 'max:2100'],
            'nationality' => ['sometimes', 'nullable', 'string', 'size:2'],
            'org_type' => ['sometimes', 'nullable', 'in:government,military,militia,armed_group,political_party,terrorist_group,intelligence_agency,ngo,international_body'],
            'founded_year' => ['sometimes', 'nullable', 'integer', 'min:1000', 'max:2100'],
            'dissolved_year' => ['sometimes', 'nullable', 'integer', 'min:1000', 'max:2100'],
            'headquarters_country' => ['sometimes', 'nullable', 'string', 'size:2'],
        ]);

        if (isset($validated['country'])) {
            $validated['country'] = $validated['country'] ? strtoupper($validated['country']) : null;
        }
        if (isset($validated['nationality'])) {
            $validated['nationality'] = $validated['nationality'] ? strtoupper($validated['nationality']) : null;
        }
        if (isset($validated['headquarters_country'])) {
            $validated['headquarters_country'] = $validated['headquarters_country']
                ? strtoupper($validated['headquarters_country'])
                : null;
        }

        $actor->update($validated);

        return back()->with('success', 'Actor updated.');
    }

    public function reenrich(Actor $actor): RedirectResponse
    {
        $actor->update(['enrichment_status' => 'pending']);
        EnrichActorJob::dispatch($actor->id);

        return back()->with('success', 'Re-enrichment dispatched.');
    }

    public function merge(Request $request, Actor $actor): RedirectResponse
    {
        $validated = $request->validate([
            'target_id' => ['required', 'string', 'exists:actors,id'],
        ]);

        if ($validated['target_id'] === $actor->id) {
            return back()->withErrors(['target_id' => 'Cannot merge an actor with itself.']);
        }

        $target = Actor::findOrFail($validated['target_id']);

        if ($target->actor_type !== $actor->actor_type) {
            return back()->withErrors(['target_id' => 'Actor types must match to merge.']);
        }

        DB::transaction(function () use ($actor, $target) {
            // Merge aliases
            $mergedAliases = array_values(array_unique(array_merge(
                $target->aliases ?? [],
                $actor->aliases ?? [],
                [$actor->canonical_name],
            )));
            $target->aliases = $mergedAliases;

            // Move entity_extractions to target
            EntityExtraction::where('actor_id', $actor->id)->update(['actor_id' => $target->id]);

            // Recount
            $distinct = EntityExtraction::where('actor_id', $target->id)->distinct('event_id')->count('event_id');
            $mentions = EntityExtraction::where('actor_id', $target->id)->count();
            $target->event_count = $distinct;
            $target->mention_count = $mentions;

            if ($actor->first_mentioned_at && (! $target->first_mentioned_at || $actor->first_mentioned_at < $target->first_mentioned_at)) {
                $target->first_mentioned_at = $actor->first_mentioned_at;
            }
            if ($actor->last_mentioned_at && (! $target->last_mentioned_at || $actor->last_mentioned_at > $target->last_mentioned_at)) {
                $target->last_mentioned_at = $actor->last_mentioned_at;
            }
            $target->save();

            $actor->delete();
        });

        return redirect()->route('admin.actors.show', $target)->with('success', 'Actors merged.');
    }

    public function candidates(Request $request): Response
    {
        $candidates = ActorCandidate::query()
            ->when($request->filled('type'), fn ($q) => $q->where('actor_type', $request->string('type')->toString()))
            ->when($request->boolean('blocked'), fn ($q) => $q->where('blocked', true), fn ($q) => $q->where('blocked', false))
            ->orderByDesc('event_count')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/Actors/Candidates', [
            'candidates' => $candidates,
            'filters' => $request->only(['type', 'blocked']),
            'promotionThreshold' => (int) config('actors.promotion_threshold', 3),
        ]);
    }

    public function promoteCandidate(ActorCandidate $candidate): RedirectResponse
    {
        // Temporarily drop the threshold gate by running the same promotion pipeline
        // for this single candidate regardless of event count.
        $candidate->update(['event_count' => max($candidate->event_count, (int) config('actors.promotion_threshold', 3))]);
        PromoteActorCandidatesJob::dispatchSync();

        return back()->with('success', 'Candidate promotion dispatched.');
    }

    public function blockCandidate(ActorCandidate $candidate): RedirectResponse
    {
        $candidate->update(['blocked' => true]);

        return back()->with('success', 'Candidate blocked.');
    }

    public function unblockCandidate(ActorCandidate $candidate): RedirectResponse
    {
        $candidate->update(['blocked' => false]);

        return back()->with('success', 'Candidate unblocked.');
    }
}
