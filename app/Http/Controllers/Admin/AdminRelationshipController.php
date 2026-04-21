<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RebuildDerivedRelationshipsJob;
use App\Models\Actor;
use App\Models\ConflictThread;
use App\Models\CountryIntelligence;
use App\Models\Relationship;
use App\Services\Graph\RelationshipDerivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminRelationshipController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Relationship::query();

        if ($request->filled('from_type')) {
            $query->where('from_type', $request->string('from_type')->toString());
        }
        if ($request->filled('to_type')) {
            $query->where('to_type', $request->string('to_type')->toString());
        }
        if ($request->filled('relation_type')) {
            $query->where('relation_type', $request->string('relation_type')->toString());
        }
        if ($request->filled('source')) {
            $query->where('source', $request->string('source')->toString());
        }

        $rels = $query->orderByDesc('id')->paginate(50)->withQueryString();

        $rels->getCollection()->transform(function (Relationship $r) {
            $r->from_label = $this->resolveLabel($r->from_type, (string) $r->from_id);
            $r->to_label = $this->resolveLabel($r->to_type, (string) $r->to_id);
            return $r;
        });

        return Inertia::render('Admin/Relationships/Index', [
            'relationships' => $rels,
            'filters' => $request->only(['from_type', 'to_type', 'relation_type', 'source']),
            'relationTypes' => Relationship::RELATION_TYPES,
            'nodeTypes' => Relationship::TYPES_NODES,
            'actorSuggestions' => Actor::orderByDesc('event_count')->limit(100)->get(['id', 'canonical_name', 'actor_type'])
                ->map(fn ($a) => ['id' => $a->id, 'label' => "{$a->canonical_name} ({$a->actor_type})"]),
            'conflictSuggestions' => ConflictThread::orderByDesc('event_count_total')->limit(100)->get(['id', 'name'])
                ->map(fn ($c) => ['id' => (string) $c->id, 'label' => $c->name]),
            'countrySuggestions' => CountryIntelligence::orderBy('country_code')->get(['country_code', 'country_name'])
                ->map(fn ($c) => ['id' => $c->country_code, 'label' => "{$c->country_code} — {$c->country_name}"]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateEdge($request);
        $validated['source'] = 'manual';

        Relationship::create($validated);

        return back()->with('success', 'Relationship created.');
    }

    public function update(Request $request, Relationship $relationship): RedirectResponse
    {
        $validated = $this->validateEdge($request);
        // Never downgrade manual to derived via update — derivation owns that
        if (($relationship->source ?? null) === 'manual') {
            $validated['source'] = 'manual';
        }

        $relationship->update($validated);

        return back()->with('success', 'Relationship updated.');
    }

    public function destroy(Relationship $relationship): RedirectResponse
    {
        $relationship->delete();

        return back()->with('success', 'Relationship deleted.');
    }

    public function rebuildDerived(RelationshipDerivationService $service): RedirectResponse
    {
        $stats = $service->rebuild();

        $inserted = array_sum($stats['inserted'] ?? []);
        return back()->with('success', "Derived rebuild complete. Deleted: {$stats['deleted']}, inserted: {$inserted}.");
    }

    private function validateEdge(Request $request): array
    {
        return $request->validate([
            'from_type' => ['required', 'in:actor,country,conflict,event'],
            'from_id' => ['required', 'string', 'max:64'],
            'to_type' => ['required', 'in:actor,country,conflict,event'],
            'to_id' => ['required', 'string', 'max:64'],
            'relation_type' => ['required', 'string', 'max:64'],
            'directed' => ['sometimes', 'boolean'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'active_from' => ['nullable', 'date'],
            'active_to' => ['nullable', 'date'],
            'evidence_json' => ['nullable', 'array'],
            'metadata_json' => ['nullable', 'array'],
        ]);
    }

    private function resolveLabel(string $type, string $id): string
    {
        return match ($type) {
            'actor' => optional(Actor::find($id))->canonical_name ?? "actor:{$id}",
            'conflict' => optional(ConflictThread::find($id))->name ?? "conflict:{$id}",
            'country' => optional(CountryIntelligence::where('country_code', $id)->first())->country_name
                ?? $id,
            'event' => "event:{$id}",
            default => "{$type}:{$id}",
        };
    }
}
