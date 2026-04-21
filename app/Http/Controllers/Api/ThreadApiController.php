<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConflictThread;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

class ThreadApiController extends Controller
{
    public function events(ConflictThread $thread): JsonResponse
    {
        $events = $thread->allEvents()
            ->with('source')
            ->orderByDesc('occurred_at')
            ->get()
            ->map(fn(Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'title_de' => $e->title_de,
                'summary' => $e->summary,
                'summary_de' => $e->summary_de,
                'severity' => $e->severity,
                'severity_factors' => $e->severity_factors,
                'confidence' => $e->confidence,
                'status' => $e->status,
                'category' => $e->category,
                'country' => $e->country,
                'region' => $e->region,
                'coordinates' => $e->coordinates,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'source_name' => $e->source?->name,
                'source_url' => $e->source_url ?: $e->source?->url,
                'source_reliability' => $e->source?->reliability_score,
                'entities_json' => $e->entities_json,
                'conflict_thread_id' => $e->conflict_thread_id,
                'corroboration_count' => $e->corroboration_count,
            ]);

        return response()->json($events);
    }
}
