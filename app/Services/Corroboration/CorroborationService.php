<?php

namespace App\Services\Corroboration;

use App\Models\CorroborationLink;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CorroborationService
{
    private const SCORE_THRESHOLD = 0.55;

    private const WEIGHT_EMBEDDING = 0.5;
    private const WEIGHT_ENTITY = 0.3;
    private const WEIGHT_STRUCTURAL = 0.2;

    private const WEIGHT_ENTITY_NO_EMBEDDING = 0.6;
    private const WEIGHT_STRUCTURAL_NO_EMBEDDING = 0.4;

    public function findMatches(Event $event): void
    {
        $sourceFamilyId = $event->source?->source_family_id;

        // Fetch candidate events from last 24h with a different source family
        $candidateQuery = Event::query()
            ->recent(24)
            ->where('id', '!=', $event->id)
            ->where('status', '!=', 'pending_classification')
            ->with(['source', 'entityExtractions']);

        // Exclude events from the same source — they share editorial ownership
        // and can never independently corroborate each other.
        if ($event->source_id) {
            $candidateQuery->where('source_id', '!=', $event->source_id);
        }

        if ($sourceFamilyId) {
            $candidateQuery->whereHas('source', function ($q) use ($sourceFamilyId) {
                $q->where('source_family_id', '!=', $sourceFamilyId);
            });
        }

        $candidates = $candidateQuery->get();

        if ($candidates->isEmpty()) {
            return;
        }

        foreach ($candidates as $candidate) {
            $this->evaluateAndLink($event, $candidate);
        }
    }

    private function evaluateAndLink(Event $event, Event $candidate): void
    {
        // Skip if a corroboration link already exists between these two events
        $alreadyLinked = CorroborationLink::query()
            ->where(function ($q) use ($event, $candidate) {
                $q->where('event_a_id', $event->id)->where('event_b_id', $candidate->id);
            })
            ->orWhere(function ($q) use ($event, $candidate) {
                $q->where('event_a_id', $candidate->id)->where('event_b_id', $event->id);
            })
            ->exists();

        if ($alreadyLinked) {
            return;
        }

        $embeddingScore = $this->computeEmbeddingSimilarity($event, $candidate);
        $entityScore = $this->computeEntityJaccard($event, $candidate);
        $structuralScore = $this->computeStructuralMatch($event, $candidate);

        if ($embeddingScore !== null) {
            $score = (self::WEIGHT_EMBEDDING * $embeddingScore)
                + (self::WEIGHT_ENTITY * $entityScore)
                + (self::WEIGHT_STRUCTURAL * $structuralScore);
            $method = 'embedding';
        } else {
            $score = (self::WEIGHT_ENTITY_NO_EMBEDDING * $entityScore)
                + (self::WEIGHT_STRUCTURAL_NO_EMBEDDING * $structuralScore);
            $method = $entityScore > 0 ? 'entity' : 'structural';
        }

        if ($score < self::SCORE_THRESHOLD) {
            return;
        }

        $crossFamily = $this->isCrossFamily($event, $candidate);

        CorroborationLink::create([
            'event_a_id' => $event->id,
            'event_b_id' => $candidate->id,
            'similarity_score' => round($score, 4),
            'match_method' => $method,
            'cross_family' => $crossFamily,
        ]);

        if ($crossFamily) {
            $this->updateCorroborationCount($event);
            $this->updateCorroborationCount($candidate);
        }
    }

    private function computeEmbeddingSimilarity(Event $event, Event $candidate): ?float
    {
        // Use pgvector cosine distance operator <=> via raw SQL
        // Returns null if either event has no embedding
        try {
            $result = DB::selectOne(
                "SELECT 1 - (e1.vector <=> e2.vector) AS similarity
                 FROM embeddings e1
                 JOIN embeddings e2 ON e2.event_id = ?
                 WHERE e1.event_id = ?
                   AND e1.provider = e2.provider
                 LIMIT 1",
                [$candidate->id, $event->id]
            );

            return $result ? (float) $result->similarity : null;
        } catch (\Throwable $e) {
            Log::debug('Embedding similarity query failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function computeEntityJaccard(Event $event, Event $candidate): float
    {
        $eventEntities = $event->entityExtractions->pluck('name')->map(fn ($n) => mb_strtolower($n))->toArray();
        $candidateEntities = $candidate->entityExtractions->pluck('name')->map(fn ($n) => mb_strtolower($n))->toArray();

        if (empty($eventEntities) || empty($candidateEntities)) {
            return 0.0;
        }

        $intersection = count(array_intersect($eventEntities, $candidateEntities));
        $union = count(array_unique(array_merge($eventEntities, $candidateEntities)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    private function computeStructuralMatch(Event $event, Event $candidate): float
    {
        $score = 0.0;

        if ($event->country && $candidate->country && $event->country === $candidate->country) {
            $score += 0.5;
        }

        if ($event->category && $candidate->category && $event->category === $candidate->category) {
            $score += 0.5;
        }

        return $score;
    }

    private function isCrossFamily(Event $event, Event $candidate): bool
    {
        // Events from the same source are never cross-family, regardless of family assignment
        if ($event->source_id && $event->source_id === $candidate->source_id) {
            return false;
        }

        $familyA = $event->source?->source_family_id;
        $familyB = $candidate->source?->source_family_id;

        if ($familyA === null || $familyB === null) {
            return true; // Unknown families are treated as independent
        }

        return $familyA !== $familyB;
    }

    private function updateCorroborationCount(Event $event): void
    {
        // Collect the "other" event from each cross-family corroboration link
        $linkedEventIds = CorroborationLink::query()
            ->where('cross_family', true)
            ->where(function ($q) use ($event) {
                $q->where('event_a_id', $event->id)
                    ->orWhere('event_b_id', $event->id);
            })
            ->get()
            ->map(fn ($link) => $link->event_a_id === $event->id ? $link->event_b_id : $link->event_a_id)
            ->unique()
            ->values();

        if ($linkedEventIds->isEmpty()) {
            $uniqueFamilyCount = 0;
        } else {
            // Count unique source families among linked events.
            // Sources without a family are grouped by source_id instead.
            $linkedEvents = Event::whereIn('id', $linkedEventIds)->with('source')->get();

            $knownFamilies = $linkedEvents
                ->filter(fn ($e) => $e->source?->source_family_id !== null)
                ->pluck('source.source_family_id')
                ->unique()
                ->count();

            $unknownFamilySources = $linkedEvents
                ->filter(fn ($e) => $e->source && $e->source->source_family_id === null)
                ->pluck('source_id')
                ->unique()
                ->count();

            $uniqueFamilyCount = $knownFamilies + $unknownFamilySources;
        }

        // 0 families = 1 source = unverified
        // 1 family   = 2 sources = corroborated
        // 2+ families = 3+ sources = confirmed
        $status = match (true) {
            $uniqueFamilyCount >= 2 => 'confirmed',
            $uniqueFamilyCount === 1 => 'corroborated',
            default => 'unverified',
        };

        $event->update([
            'corroboration_count' => $uniqueFamilyCount,
            'status' => $status,
        ]);
    }
}
