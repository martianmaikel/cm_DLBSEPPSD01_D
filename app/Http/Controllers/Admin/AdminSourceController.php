<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Source;
use App\Models\SourceFamily;
use App\Services\Ingestion\ConnectorRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSourceController extends Controller
{
    public function index(): Response
    {
        $sources = Source::query()
            ->with('sourceFamily')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Sources', [
            'sources' => $sources,
            'sourceFamilies' => SourceFamily::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(ConnectorRegistry $registry): Response
    {
        return Inertia::render('Admin/Sources', [
            'sourceFamilies' => SourceFamily::orderBy('name')->get(['id', 'name']),
            'sourceTypes' => ['rss', 'telegram', 'api', 'csv_import', 'scraper', 'manual'],
            'registeredConnectors' => $registry->registeredTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'type'              => ['required', 'in:rss,telegram,api,csv_import,scraper,manual'],
            'url'               => ['required', 'url', 'max:2048'],
            'source_family_id'  => ['nullable', 'integer', 'exists:source_families,id'],
            'polling_interval'  => ['required', 'integer', 'min:1'],
            'reliability_score' => ['required', 'numeric', 'min:0', 'max:10'],
            'active'            => ['boolean'],
            'connector_class'   => ['nullable', 'string', 'max:255'],
            'connector_config'  => ['nullable', 'array'],
        ]);

        // Encode connector_config for storage
        if (isset($validated['connector_config'])) {
            $validated['connector_config'] = json_encode($validated['connector_config']);
        }

        Source::create($validated);

        return redirect()->route('sources.index')
            ->with('success', 'Source created.');
    }

    public function edit(Source $source, ConnectorRegistry $registry): Response
    {
        return Inertia::render('Admin/Sources', [
            'source' => $source->load('sourceFamily'),
            'sourceFamilies' => SourceFamily::orderBy('name')->get(['id', 'name']),
            'sourceTypes' => ['rss', 'telegram', 'api', 'csv_import', 'scraper', 'manual'],
            'registeredConnectors' => $registry->registeredTypes(),
        ]);
    }

    public function update(Request $request, Source $source): RedirectResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'type'              => ['required', 'in:rss,telegram,api,csv_import,scraper,manual'],
            'url'               => ['required', 'url', 'max:2048'],
            'source_family_id'  => ['nullable', 'integer', 'exists:source_families,id'],
            'polling_interval'  => ['required', 'integer', 'min:1'],
            'reliability_score' => ['required', 'numeric', 'min:0', 'max:10'],
            'active'            => ['boolean'],
            'connector_class'   => ['nullable', 'string', 'max:255'],
            'connector_config'  => ['nullable', 'array'],
        ]);

        if (isset($validated['connector_config'])) {
            $validated['connector_config'] = json_encode($validated['connector_config']);
        }

        $source->update($validated);

        return redirect()->route('sources.index')
            ->with('success', 'Source updated.');
    }

    public function destroy(Source $source): RedirectResponse
    {
        $source->delete();

        return redirect()->route('sources.index')
            ->with('success', 'Source deleted.');
    }
}
