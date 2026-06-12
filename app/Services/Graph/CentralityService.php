<?php

namespace App\Services\Graph;

/**
 * Computes graph-theoretic centrality measures on a {@see Graph}.
 *
 * Each measure is implemented from scratch (no external graph library) for
 * testability and to make the underlying algorithm explicit and auditable.
 */
class CentralityService
{
    /**
     * Freeman degree centrality, normalised by the number of other nodes.
     *
     *     C_D(v) = deg(v) / (n - 1)
     *
     * The score lies in [0.0, 1.0]; it reaches 1.0 only for a node adjacent to
     * every other node. Reference: Freeman, L. C. (1978/79), "Centrality in
     * social networks: Conceptual clarification", Social Networks 1(3), 215–239.
     *
     * @return array<string, float> node id => centrality score
     */
    public function degreeCentrality(Graph $graph): array
    {
        $n = $graph->nodeCount();

        // With zero or one node there is no possible connection, so every score
        // is 0.0. Handling this here also avoids a division by (n - 1) = 0.
        if ($n <= 1) {
            return array_fill_keys($graph->nodes(), 0.0);
        }

        $denominator = $n - 1;
        $scores = [];
        foreach ($graph->nodes() as $id) {
            // Cast keeps the result a float even when the degree divides
            // evenly (PHP's "/" returns int for e.g. 0 / 4, breaking ===).
            $scores[$id] = (float) $graph->degree($id) / $denominator;
        }

        return $scores;
    }
}
