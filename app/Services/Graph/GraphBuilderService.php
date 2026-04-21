<?php

namespace App\Services\Graph;

use App\DataTransferObjects\GraphResult;
use App\Models\Actor;
use App\Models\ConflictThread;
use App\Models\CountryIntelligence;
use App\Models\EntityExtraction;
use App\Models\Event;
use App\Models\Relationship;
use Illuminate\Support\Collection;

class GraphBuilderService
{
    private const MAX_DEPTH = 3;
    private const DEFAULT_LIMIT_PER_TYPE = 30;
    private const ACTOR_EVENT_MIN_MENTIONS = 2;

    /**
     * Build a local subgraph via BFS from a starting node.
     */
    public function expandFrom(
        string $type,
        string $id,
        int $depth = 1,
        array $nodeTypes = ['actor', 'country', 'conflict'],
        array $relationTypes = [],
        bool $includeEvents = false,
        int $limitPerType = self::DEFAULT_LIMIT_PER_TYPE,
    ): GraphResult {
        $depth = max(1, min(self::MAX_DEPTH, $depth));
        if ($includeEvents && ! in_array('event', $nodeTypes, true)) {
            $nodeTypes[] = 'event';
        }

        $graph = new GraphResult();
        $this->addNodeByRef($graph, $type, $id);

        $frontier = [[$type, $id]];
        $seen = [$this->key($type, $id) => true];

        for ($hop = 0; $hop < $depth; $hop++) {
            $nextFrontier = [];
            foreach ($frontier as [$ft, $fi]) {
                $neighbors = $this->neighborsOf($ft, $fi, $nodeTypes, $relationTypes, $includeEvents, $limitPerType);
                foreach ($neighbors['edges'] as $edge) {
                    $graph->addEdge($edge);
                }
                foreach ($neighbors['nodes'] as $node) {
                    $k = $node['id'];
                    if (! isset($seen[$k])) {
                        $seen[$k] = true;
                        $nextFrontier[] = explode(':', $k, 2);
                    }
                    $graph->addNode($node);
                }
            }
            $frontier = $nextFrontier;
            if (empty($frontier)) {
                break;
            }
        }

        return $graph;
    }

    /**
     * Assemble a graph of top entities for the /graph explorer.
     */
    public function globalSnapshot(
        array $nodeTypes = ['actor', 'country', 'conflict'],
        array $relationTypes = [],
        bool $includeEvents = false,
        int $topActors = 40,
        int $topConflicts = 30,
        int $topCountries = 40,
    ): GraphResult {
        $graph = new GraphResult();

        if (in_array('actor', $nodeTypes, true)) {
            Actor::query()
                ->where('enrichment_status', 'enriched')
                ->orderByDesc('event_count')
                ->limit($topActors)
                ->get()
                ->each(fn (Actor $a) => $graph->addNode($this->nodeForActor($a)));
        }

        if (in_array('conflict', $nodeTypes, true)) {
            ConflictThread::query()
                ->where('status', 'open')
                ->orderByDesc('event_count_total')
                ->limit($topConflicts)
                ->get()
                ->each(fn (ConflictThread $c) => $graph->addNode($this->nodeForConflict($c)));
        }

        if (in_array('country', $nodeTypes, true)) {
            CountryIntelligence::query()
                ->orderByDesc('event_count_total')
                ->limit($topCountries)
                ->get()
                ->each(fn (CountryIntelligence $c) => $graph->addNode($this->nodeForCountry($c->country_code, $c)));
        }

        $nodeIds = $graph->nodeIds();
        if (empty($nodeIds)) {
            return $graph;
        }

        // Pull all edges where both endpoints are among the known node IDs
        $relQuery = Relationship::query();
        if (! empty($relationTypes)) {
            $relQuery->whereIn('relation_type', $relationTypes);
        }
        $relQuery->chunkById(1000, function ($batch) use (&$graph, $nodeIds) {
            foreach ($batch as $rel) {
                $fromKey = $this->key($rel->from_type, (string) $rel->from_id);
                $toKey = $this->key($rel->to_type, (string) $rel->to_id);
                if (in_array($fromKey, $nodeIds, true) && in_array($toKey, $nodeIds, true)) {
                    $graph->addEdge($this->edgeForRelationship($rel));
                }
            }
        });

        return $graph;
    }

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    private function neighborsOf(
        string $type,
        string $id,
        array $nodeTypes,
        array $relationTypes,
        bool $includeEvents,
        int $limitPerType,
    ): array {
        $nodes = [];
        $edges = [];

        // 1) Curated/derived edges from the `relationships` table
        $relQuery = Relationship::touching($type, $id);
        if (! empty($relationTypes)) {
            $relQuery->whereIn('relation_type', $relationTypes);
        }
        $rels = $relQuery->limit(500)->get();
        foreach ($rels as $rel) {
            $otherType = $rel->from_type === $type && $rel->from_id === $id ? $rel->to_type : $rel->from_type;
            $otherId   = $rel->from_type === $type && $rel->from_id === $id ? (string) $rel->to_id : (string) $rel->from_id;

            if (! in_array($otherType, $nodeTypes, true)) {
                continue;
            }

            $n = $this->nodeFor($otherType, $otherId);
            if ($n) {
                $nodes[] = $n;
                $edges[] = $this->edgeForRelationship($rel);
            }
        }

        // 2) Live edges not materialized in `relationships`
        if ($type === 'actor' && (in_array('event', $nodeTypes, true) || $includeEvents)) {
            $this->addActorEventLive($id, $limitPerType, $nodes, $edges);
        }
        if ($type === 'event' && in_array('actor', $nodeTypes, true)) {
            $this->addEventActorLive($id, $limitPerType, $nodes, $edges);
        }
        if ($type === 'conflict' && in_array('event', $nodeTypes, true)) {
            $this->addConflictEventLive($id, $limitPerType, $nodes, $edges);
        }
        if ($type === 'event' && in_array('conflict', $nodeTypes, true)) {
            $this->addEventConflictLive($id, $nodes, $edges);
        }
        if ($type === 'event' && in_array('event', $nodeTypes, true)) {
            $this->addEventCorroborationLive($id, $limitPerType, $nodes, $edges);
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function addActorEventLive(string $actorId, int $limit, array &$nodes, array &$edges): void
    {
        $extractions = EntityExtraction::query()
            ->where('actor_id', $actorId)
            ->join('events', 'events.id', '=', 'entity_extractions.event_id')
            ->orderByDesc('events.occurred_at')
            ->limit($limit)
            ->select('events.id as eid', 'events.title', 'events.occurred_at', 'events.severity', 'events.country', 'events.slug')
            ->get();

        foreach ($extractions as $row) {
            $nodes[] = [
                'id' => $this->key('event', $row->eid),
                'type' => 'event',
                'label' => $row->title,
                'slug' => $row->slug,
                'country' => $row->country,
                'severity' => $row->severity,
                'occurred_at' => $row->occurred_at,
            ];
            $edges[] = [
                'from' => $this->key('actor', $actorId),
                'to' => $this->key('event', $row->eid),
                'type' => 'mentioned_in',
                'source' => 'live',
                'directed' => true,
            ];
        }
    }

    private function addEventActorLive(string $eventId, int $limit, array &$nodes, array &$edges): void
    {
        $extractions = EntityExtraction::query()
            ->where('event_id', $eventId)
            ->whereNotNull('actor_id')
            ->with('actor:id,slug,canonical_name,actor_type,image_url,country,status,event_count,mention_count')
            ->limit($limit)
            ->get();

        foreach ($extractions as $ex) {
            $actor = $ex->actor;
            if (! $actor) {
                continue;
            }
            $nodes[] = $this->nodeForActor($actor);
            $edges[] = [
                'from' => $this->key('actor', $actor->id),
                'to' => $this->key('event', $eventId),
                'type' => 'mentioned_in',
                'source' => 'live',
                'directed' => true,
            ];
        }
    }

    private function addConflictEventLive(string $threadId, int $limit, array &$nodes, array &$edges): void
    {
        $events = Event::query()
            ->where('conflict_thread_id', $threadId)
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get(['id', 'slug', 'title', 'occurred_at', 'severity', 'country']);

        foreach ($events as $event) {
            $nodes[] = [
                'id' => $this->key('event', $event->id),
                'type' => 'event',
                'label' => $event->title,
                'slug' => $event->slug,
                'country' => $event->country,
                'severity' => $event->severity,
                'occurred_at' => optional($event->occurred_at)->toIso8601String(),
            ];
            $edges[] = [
                'from' => $this->key('event', $event->id),
                'to' => $this->key('conflict', $threadId),
                'type' => 'part_of',
                'source' => 'live',
                'directed' => true,
            ];
        }
    }

    private function addEventConflictLive(string $eventId, array &$nodes, array &$edges): void
    {
        $event = Event::query()->where('id', $eventId)->whereNotNull('conflict_thread_id')->first(['id', 'conflict_thread_id']);
        if (! $event) {
            return;
        }
        $thread = ConflictThread::find($event->conflict_thread_id);
        if (! $thread) {
            return;
        }
        $nodes[] = $this->nodeForConflict($thread);
        $edges[] = [
            'from' => $this->key('event', $eventId),
            'to' => $this->key('conflict', $thread->id),
            'type' => 'part_of',
            'source' => 'live',
            'directed' => true,
        ];
    }

    private function addEventCorroborationLive(string $eventId, int $limit, array &$nodes, array &$edges): void
    {
        $links = \App\Models\CorroborationLink::query()
            ->where('event_a_id', $eventId)
            ->orWhere('event_b_id', $eventId)
            ->limit($limit)
            ->get();

        foreach ($links as $link) {
            $otherId = $link->event_a_id === $eventId ? $link->event_b_id : $link->event_a_id;
            $otherEvent = Event::find($otherId);
            if (! $otherEvent) {
                continue;
            }
            $nodes[] = [
                'id' => $this->key('event', $otherEvent->id),
                'type' => 'event',
                'label' => $otherEvent->title,
                'slug' => $otherEvent->slug,
                'country' => $otherEvent->country,
                'severity' => $otherEvent->severity,
            ];
            $edges[] = [
                'from' => $this->key('event', $eventId),
                'to' => $this->key('event', $otherId),
                'type' => 'corroborates',
                'source' => 'live',
                'directed' => false,
                'weight' => (float) $link->similarity_score,
            ];
        }
    }

    // ── Node builders ──

    private function nodeFor(string $type, string $id): ?array
    {
        return match ($type) {
            'actor' => ($a = Actor::find($id)) ? $this->nodeForActor($a) : null,
            'conflict' => ($c = ConflictThread::find($id)) ? $this->nodeForConflict($c) : null,
            'country' => $this->nodeForCountry($id, CountryIntelligence::where('country_code', $id)->first()),
            'event' => ($e = Event::find($id)) ? [
                'id' => $this->key('event', $e->id),
                'type' => 'event',
                'label' => $e->title,
                'slug' => $e->slug,
                'country' => $e->country,
                'severity' => $e->severity,
            ] : null,
            default => null,
        };
    }

    private function addNodeByRef(GraphResult $graph, string $type, string $id): void
    {
        $node = $this->nodeFor($type, $id);
        if ($node) {
            $graph->addNode($node);
        }
    }

    private function nodeForActor(Actor $a): array
    {
        return [
            'id' => $this->key('actor', $a->id),
            'type' => 'actor',
            'subtype' => $a->actor_type,
            'label' => $a->canonical_name,
            'slug' => $a->slug,
            'image_url' => $a->image_url,
            'country' => $a->country,
            'status' => $a->status,
            'role_title' => $a->role_title,
            'org_type' => $a->org_type,
            'event_count' => (int) $a->event_count,
            'mention_count' => (int) $a->mention_count,
        ];
    }

    private function nodeForConflict(ConflictThread $c): array
    {
        return [
            'id' => $this->key('conflict', (string) $c->id),
            'type' => 'conflict',
            'label' => $c->name,
            'slug' => $c->slug,
            'status' => $c->status,
            'max_severity' => (int) ($c->max_severity ?? 0),
            'event_count' => (int) ($c->event_count_total ?? 0),
            'countries' => is_array($c->countries) ? $c->countries : [],
        ];
    }

    private function nodeForCountry(string $iso, ?CountryIntelligence $ci): array
    {
        return [
            'id' => $this->key('country', $iso),
            'type' => 'country',
            'label' => $ci?->country_name ?? $iso,
            'iso' => $iso,
            'flag' => $this->flagEmoji($iso),
            'threat_level' => $ci?->threat_level,
            'event_count' => (int) ($ci?->event_count_total ?? 0),
        ];
    }

    private function edgeForRelationship(Relationship $rel): array
    {
        return [
            'from' => $this->key($rel->from_type, (string) $rel->from_id),
            'to' => $this->key($rel->to_type, (string) $rel->to_id),
            'type' => $rel->relation_type,
            'source' => $rel->source,
            'directed' => (bool) $rel->directed,
            'weight' => $rel->weight !== null ? (float) $rel->weight : null,
        ];
    }

    private function key(string $type, string $id): string
    {
        return "{$type}:{$id}";
    }

    private function flagEmoji(string $iso): string
    {
        $iso = strtoupper($iso);
        if (! preg_match('/^[A-Z]{2}$/', $iso)) {
            return '';
        }
        $base = 0x1F1E6;
        return mb_chr($base + ord($iso[0]) - ord('A'), 'UTF-8')
             . mb_chr($base + ord($iso[1]) - ord('A'), 'UTF-8');
    }
}
