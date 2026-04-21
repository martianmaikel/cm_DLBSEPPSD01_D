<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAffiliateRequest;
use App\Models\NewsletterAffiliate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminAffiliateController extends Controller
{
    public function index(): Response
    {
        $affiliates = NewsletterAffiliate::query()
            ->orderByDesc('active')
            ->orderByDesc('weight')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Affiliates', [
            'affiliates' => $affiliates,
        ]);
    }

    public function store(StoreAffiliateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['active'] = $request->boolean('active', true);

        NewsletterAffiliate::create($validated);

        return redirect()->route('admin.affiliates.index')
            ->with('success', 'Affiliate created.');
    }

    public function update(StoreAffiliateRequest $request, NewsletterAffiliate $affiliate): RedirectResponse
    {
        $validated = $request->validated();
        $validated['active'] = $request->boolean('active', $affiliate->active);

        $affiliate->update($validated);

        return redirect()->route('admin.affiliates.index')
            ->with('success', 'Affiliate updated.');
    }

    public function destroy(NewsletterAffiliate $affiliate): RedirectResponse
    {
        $affiliate->delete();

        return redirect()->route('admin.affiliates.index')
            ->with('success', 'Affiliate deleted.');
    }
}
