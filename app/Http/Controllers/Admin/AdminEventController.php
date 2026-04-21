<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConflictThread;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminEventController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Event::query()->with('source');

        if ($request->filled('country')) {
            $query->byCountry(strtoupper($request->string('country')->toString()));
        }

        if ($request->filled('category')) {
            $query->byCategory($request->string('category')->toString());
        }

        if ($request->filled('status')) {
            $query->byStatus($request->string('status')->toString());
        }

        if ($request->filled('severity_min')) {
            $query->where('severity', '>=', (int) $request->input('severity_min'));
        }

        if ($request->filled('severity_max')) {
            $query->where('severity', '<=', (int) $request->input('severity_max'));
        }

        if ($request->filled('date_from')) {
            $query->where('occurred_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('occurred_at', '<=', $request->input('date_to'));
        }

        $events = $query->orderByDesc('occurred_at')->paginate(50)->withQueryString();

        return Inertia::render('Admin/Events', [
            'events' => $events,
            'filters' => $request->only([
                'country', 'category', 'status',
                'severity_min', 'severity_max',
                'date_from', 'date_to',
            ]),
        ]);
    }

    public function updateStatus(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:disputed,retracted'],
        ]);

        $event->update(['status' => $validated['status']]);

        return back()->with('success', 'Event status updated.');
    }

    public function reassignThread(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'conflict_thread_id' => ['nullable', 'integer', 'exists:conflict_threads,id'],
        ]);

        $event->update(['conflict_thread_id' => $validated['conflict_thread_id']]);

        return back()->with('success', 'Event thread reassigned.');
    }
}
