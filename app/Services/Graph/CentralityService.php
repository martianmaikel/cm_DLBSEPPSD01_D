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

    /**
     * Freeman-normalised betweenness centrality via Brandes' algorithm.
     *
     * For every source node it runs a BFS to count shortest paths and record
     * the shortest-path DAG, then back-propagates path dependencies in reverse
     * BFS order — yielding O(n·m) time instead of the naive O(n³) of explicit
     * path enumeration. Reference: Brandes, U. (2001), "A faster algorithm for
     * betweenness centrality", Journal of Mathematical Sociology 25(2), 163–177.
     *
     * Scores are normalised into [0.0, 1.0]. The undirected "divide by 2" and
     * the (n-1)(n-2)/2 pair count cancel into a single (n-1)(n-2) divisor, which
     * is also the correct normaliser for the directed case.
     *
     * @return array<string, float> node id => centrality score
     */
    public function betweennessCentrality(Graph $graph): array
    {
        $nodes = $graph->nodes();
        $n = count($nodes);
        $betweenness = array_fill_keys($nodes, 0.0);

        // A node can only sit *between* two others when there are at least
        // three nodes; this also guards the (n-1)(n-2) divisor against zero.
        if ($n <= 2) {
            return $betweenness;
        }

        foreach ($nodes as $source) {
            // ── Phase 1: BFS shortest paths from $source ──
            $stack = [];                              // nodes in BFS (non-decreasing distance) order
            $predecessors = array_fill_keys($nodes, []);
            $sigma = array_fill_keys($nodes, 0.0);    // number of shortest paths source → node
            $sigma[$source] = 1.0;
            $distance = array_fill_keys($nodes, -1);
            $distance[$source] = 0;

            $queue = [$source];
            $head = 0;
            while ($head < count($queue)) {
                $v = $queue[$head++];
                $stack[] = $v;
                foreach (array_keys($graph->neighbors($v)) as $w) {
                    // First discovery of $w → fix its distance and enqueue it.
                    if ($distance[$w] < 0) {
                        $distance[$w] = $distance[$v] + 1;
                        $queue[] = $w;
                    }
                    // Another shortest path to $w that runs through $v.
                    if ($distance[$w] === $distance[$v] + 1) {
                        $sigma[$w] += $sigma[$v];
                        $predecessors[$w][] = $v;
                    }
                }
            }

            // ── Phase 2: accumulate dependencies in reverse BFS order ──
            $delta = array_fill_keys($nodes, 0.0);
            for ($i = count($stack) - 1; $i >= 0; $i--) {
                $w = $stack[$i];
                foreach ($predecessors[$w] as $v) {
                    $delta[$v] += ($sigma[$v] / $sigma[$w]) * (1.0 + $delta[$w]);
                }
                if ($w !== $source) {
                    $betweenness[$w] += $delta[$w];
                }
            }
        }

        $scale = ($n - 1) * ($n - 2);
        foreach ($betweenness as $id => $value) {
            $betweenness[$id] = $value / $scale;
        }

        return $betweenness;
    }
}
