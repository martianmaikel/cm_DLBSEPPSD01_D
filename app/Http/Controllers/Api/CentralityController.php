<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Graph\CentralityCache;
use App\Services\Graph\CentralityService;
use App\Services\Graph\GraphBuilderService;
use App\Services\Graph\GraphResultAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exposes the actor influence centrality measures (degree, betweenness,
 * PageRank) over the relationship graph as a ranked JSON list.
 */
class CentralityController extends Controller
{
    private const METRICS = ['degree', 'betweenness', 'pagerank'];

    public function __construct(
        private readonly GraphBuilderService $builder,
        private readonly GraphResultAdapter $adapter,
        private readonly CentralityService $centrality,
        private readonly CentralityCache $cache,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $metric = (string) $request->input('metric', 'degree');
        if ($metric !== 'all' && ! in_array($metric, self::METRICS, true)) {
            abort(422, 'Unknown metric. Use one of: degree, betweenness, pagerank, all.');
        }

        $limit = max(1, min(200, (int) $request->input('limit', 50)));
        $topActors = max(10, min(200, (int) $request->input('top_actors', 100)));

        // Actor-only relationship graph — this feature ranks actor influence.
        $result = $this->builder->globalSnapshot(nodeTypes: ['actor'], topActors: $topActors);
        $graph = $this->adapter->toGraph($result);
        $signature = $this->cache->signatureFor($graph);

        $requested = $metric === 'all' ? self::METRICS : [$metric];
        $scores = [];
        foreach ($requested as $name) {
            $scores[$name] = $this->cache->remember($name, $signature, fn () => match ($name) {
                'degree' => $this->centrality->degreeCentrality($graph),
                'betweenness' => $this->centrality->betweennessCentrality($graph),
                'pagerank' => $this->centrality->pageRankCentrality($graph),
            });
        }

        $nodes = [];
        foreach ($result->nodes as $id => $meta) {
            $row = [
                'id' => $id,
                'label' => $meta['label'] ?? $id,
                'type' => $meta['type'] ?? null,
            ];
            foreach ($requested as $name) {
                $row[$name] = round($scores[$name][$id] ?? 0.0, 6);
            }
            $nodes[] = $row;
        }

        // Rank by the primary (first requested) metric, descending.
        $primary = $requested[0];
        usort($nodes, fn (array $a, array $b) => $b[$primary] <=> $a[$primary]);
        $nodes = array_slice($nodes, 0, $limit);

        return response()->json([
            'metric' => $metric,
            'count' => count($nodes),
            'nodes' => $nodes,
        ]);
    }
}
