<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Graph\GraphBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GraphApiController extends Controller
{
    private const ALLOWED_NODE_TYPES = ['actor', 'country', 'conflict', 'event'];

    public function __construct(private readonly GraphBuilderService $builder) {}

    public function node(string $type, string $id, Request $request): JsonResponse
    {
        if (! in_array($type, self::ALLOWED_NODE_TYPES, true)) {
            abort(404);
        }

        $depth = (int) $request->input('depth', 1);
        $includeEvents = $request->boolean('include_events', false);
        $limitPerType = (int) $request->input('limit_per_type', 30);

        $nodeTypes = $this->sanitizeList(
            $request->input('node_types', ['actor', 'country', 'conflict']),
            self::ALLOWED_NODE_TYPES,
        );
        $relationTypes = $this->sanitizeList($request->input('relation_types', []));

        $graph = $this->builder->expandFrom(
            type: $type,
            id: $id,
            depth: $depth,
            nodeTypes: $nodeTypes ?: ['actor', 'country', 'conflict'],
            relationTypes: $relationTypes,
            includeEvents: $includeEvents,
            limitPerType: max(5, min(100, $limitPerType)),
        );

        return response()->json($graph->toArray());
    }

    public function globalGraph(Request $request): JsonResponse
    {
        $nodeTypes = $this->sanitizeList(
            $request->input('node_types', ['actor', 'country', 'conflict']),
            self::ALLOWED_NODE_TYPES,
        );
        $relationTypes = $this->sanitizeList($request->input('relation_types', []));

        $graph = $this->builder->globalSnapshot(
            nodeTypes: $nodeTypes ?: ['actor', 'country', 'conflict'],
            relationTypes: $relationTypes,
            includeEvents: $request->boolean('include_events', false),
            topActors: (int) $request->input('top_actors', 40),
            topConflicts: (int) $request->input('top_conflicts', 30),
            topCountries: (int) $request->input('top_countries', 40),
        );

        return response()->json($graph->toArray());
    }

    /**
     * @param  mixed  $input
     * @param  array<int, string>|null  $whitelist
     * @return array<int, string>
     */
    private function sanitizeList(mixed $input, ?array $whitelist = null): array
    {
        if (is_string($input)) {
            $input = array_filter(array_map('trim', explode(',', $input)));
        }
        if (! is_array($input)) {
            return [];
        }
        $clean = array_values(array_unique(array_filter(array_map('strval', $input))));
        if ($whitelist) {
            $clean = array_values(array_intersect($clean, $whitelist));
        }
        return $clean;
    }
}
