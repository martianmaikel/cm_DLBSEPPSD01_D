<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SourceFamily;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSourceFamilyController extends Controller
{
    public function index(): Response
    {
        $families = SourceFamily::query()
            ->withCount('sources')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/SourceFamilies', [
            'families' => $families,
        ]);
    }

    public function create(): Response
    {
        return $this->index();
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255', 'unique:source_families,name'],
            'editorial_ownership' => ['nullable', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
        ]);

        SourceFamily::create($validated);

        return redirect()->route('source-families.index')
            ->with('success', 'Source family created.');
    }

    public function edit(SourceFamily $sourceFamily): Response
    {
        return $this->index();
    }

    public function update(Request $request, SourceFamily $sourceFamily): RedirectResponse
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255', 'unique:source_families,name,' . $sourceFamily->id],
            'editorial_ownership' => ['nullable', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
        ]);

        $sourceFamily->update($validated);

        return redirect()->route('source-families.index')
            ->with('success', 'Source family updated.');
    }

    public function destroy(SourceFamily $sourceFamily): RedirectResponse
    {
        $sourceFamily->delete();

        return redirect()->route('source-families.index')
            ->with('success', 'Source family deleted.');
    }
}
