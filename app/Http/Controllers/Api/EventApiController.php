<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Event::query()->with('source');

        if ($request->filled('country')) {
            $query->byCountry(strtoupper($request->string('country')->toString()));
        }

        if ($request->filled('category')) {
            $query->byCategory($request->string('category')->toString());
        }

        if ($request->filled('severity_min')) {
            $query->where('severity', '>=', (int) $request->input('severity_min'));
        }

        if ($request->filled('severity_max')) {
            $query->where('severity', '<=', (int) $request->input('severity_max'));
        }

        if ($request->filled('status')) {
            $query->byStatus($request->string('status')->toString());
        }

        if ($request->filled('date_from')) {
            $query->where('occurred_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('occurred_at', '<=', $request->input('date_to'));
        }

        $events = $query->orderByDesc('occurred_at')->paginate(50);

        return response()->json($events);
    }

    public function show(Event $event): JsonResponse
    {
        $event->load([
            'source.sourceFamily',
            'conflictThread',
            'corroborationLinksAsA.eventB.source',
            'corroborationLinksAsB.eventA.source',
        ]);

        $corroborationChain = collect()
            ->merge($event->corroborationLinksAsA->map(fn($link) => [
                'event' => $link->eventB,
                'similarity_score' => $link->similarity_score,
                'match_method' => $link->match_method,
                'cross_family' => $link->cross_family,
            ]))
            ->merge($event->corroborationLinksAsB->map(fn($link) => [
                'event' => $link->eventA,
                'similarity_score' => $link->similarity_score,
                'match_method' => $link->match_method,
                'cross_family' => $link->cross_family,
            ]))
            ->values();

        return response()->json([
            'event' => $event,
            'corroboration_chain' => $corroborationChain,
        ]);
    }
}
