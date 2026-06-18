<?php

namespace App\Services\Graph;

use Illuminate\Support\Facades\Cache;

/**
 * Caches computed centrality scores so the expensive measures (betweenness,
 * PageRank) are not recomputed on every request.
 *
 * Invalidation is version-based, but the version is derived from the graph's
 * structure itself: {@see signatureFor()} hashes the node/edge set, so any
 * change to the graph produces a new cache key and old entries fall out
 * naturally — no explicit flush or manual version counter required.
 */
class CentralityCache
{
    public const TTL_SECONDS = 600;

    /**
     * Return cached scores for ($metric, $signature), computing and storing
     * them on a miss.
     *
     * @param  callable(): array<string, float>  $compute
     * @return array<string, float>
     */
    public function remember(string $metric, string $signature, callable $compute): array
    {
        return Cache::remember($this->key($metric, $signature), self::TTL_SECONDS, $compute);
    }

    public function key(string $metric, string $signature): string
    {
        return "centrality:{$metric}:{$signature}";
    }

    /**
     * Build a stable signature from the graph structure. Identical graphs map to
     * the same signature; any change to nodes or edges changes it.
     */
    public function signatureFor(Graph $graph): string
    {
        $nodes = $graph->nodes();
        sort($nodes);

        $edgeCount = 0;
        $parts = [];
        foreach ($nodes as $id) {
            $neighbors = array_keys($graph->neighbors($id));
            sort($neighbors);
            $edgeCount += count($neighbors);
            $parts[] = $id.'>'.implode(',', $neighbors);
        }

        return substr(sha1(implode('|', $parts)), 0, 16).'-'.count($nodes).'n'.$edgeCount.'e';
    }
}
