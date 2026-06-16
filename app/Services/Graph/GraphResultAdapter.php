<?php

namespace App\Services\Graph;

use App\DataTransferObjects\GraphResult;

/**
 * Converts the production {@see GraphResult} (rich, app-coupled node/edge arrays)
 * into the lean {@see Graph} the centrality algorithms operate on.
 *
 * The conversion drops per-node metadata and reduces the data to a simple graph:
 * parallel edges between the same pair collapse to one, self-loops are dropped,
 * and edges whose endpoints are not in the declared node set are skipped. The
 * caller decides whether to treat the relationship graph as directed or not.
 */
class GraphResultAdapter
{
    public function toGraph(GraphResult $result, bool $directed = false): Graph
    {
        $graph = new Graph(directed: $directed);

        foreach (array_keys($result->nodes) as $id) {
            $graph->addNode((string) $id);
        }

        foreach ($result->edges as $edge) {
            $from = (string) ($edge['from'] ?? '');
            $to = (string) ($edge['to'] ?? '');

            // Skip malformed edges and self-loops (a self-loop would distort
            // the degree of the node without representing a relationship).
            if ($from === '' || $to === '' || $from === $to) {
                continue;
            }

            // Skip edges that point outside the declared node set so the graph
            // never grows phantom nodes from stray live edges.
            if (! $graph->hasNode($from) || ! $graph->hasNode($to)) {
                continue;
            }

            // weight may be null in the result; default unweighted edges to 1.0.
            $weight = isset($edge['weight']) ? (float) $edge['weight'] : 1.0;
            $graph->addEdge($from, $to, $weight);
        }

        return $graph;
    }
}
