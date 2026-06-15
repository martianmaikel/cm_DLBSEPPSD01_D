<?php

use App\Services\Graph\CentralityService;
use App\Services\Graph\Graph;

/**
 * Reference fixture (undirected):
 *
 *        A
 *       /|\
 *      B-C D        E (isolated)
 *
 * Edges:   A-B, A-C, A-D, B-C
 * Degrees: A=3, B=2, C=2, D=1, E=0   (n = 5)
 *
 * Freeman degree centrality = deg / (n - 1) = deg / 4:
 *   A=0.75, B=0.50, C=0.50, D=0.25, E=0.00
 */
function fixtureGraph(): Graph
{
    $g = new Graph(directed: false);

    foreach (['A', 'B', 'C', 'D', 'E'] as $node) {
        $g->addNode($node);
    }

    $g->addEdge('A', 'B');
    $g->addEdge('A', 'C');
    $g->addEdge('A', 'D');
    $g->addEdge('B', 'C');

    return $g;
}

describe('CentralityService::degreeCentrality', function () {
    it('computes Freeman-normalised degree centrality on a known graph', function () {
        $scores = (new CentralityService())->degreeCentrality(fixtureGraph());

        expect($scores['A'])->toBe(0.75)
            ->and($scores['B'])->toBe(0.5)
            ->and($scores['C'])->toBe(0.5)
            ->and($scores['D'])->toBe(0.25)
            ->and($scores['E'])->toBe(0.0);
    });

    it('ranks the most-connected node highest', function () {
        $scores = (new CentralityService())->degreeCentrality(fixtureGraph());

        arsort($scores);
        expect(array_key_first($scores))->toBe('A');
    });

    it('scores an isolated node as 0.0', function () {
        $scores = (new CentralityService())->degreeCentrality(fixtureGraph());

        expect($scores['E'])->toBe(0.0);
    });

    it('returns an empty result for an empty graph', function () {
        $scores = (new CentralityService())->degreeCentrality(new Graph());

        expect($scores)->toBe([]);
    });

    it('scores the sole node of a single-node graph as 0.0', function () {
        $g = new Graph();
        $g->addNode('only');

        $scores = (new CentralityService())->degreeCentrality($g);

        expect($scores)->toBe(['only' => 0.0]);
    });
});

describe('CentralityService::betweennessCentrality', function () {
    it('computes Freeman-normalised betweenness on a known graph', function () {
        // A is the only cut vertex: it lies on the unique shortest paths
        // B–D and C–D. Raw betweenness(A) = 2 (unordered pairs), normalised
        // by (n-1)(n-2)/2 = 6 → 1/3. Every other node is on no shortest path
        // between two *other* nodes.
        $scores = (new CentralityService())->betweennessCentrality(fixtureGraph());

        expect($scores['A'])->toEqualWithDelta(1 / 3, 1e-9)
            ->and($scores['B'])->toBe(0.0)
            ->and($scores['C'])->toBe(0.0)
            ->and($scores['D'])->toBe(0.0)
            ->and($scores['E'])->toBe(0.0);
    });

    it('scores the middle node of a path graph as 1.0', function () {
        // X — Y — Z : Y sits on the only shortest path X–Z.
        $g = new Graph();
        $g->addEdge('X', 'Y');
        $g->addEdge('Y', 'Z');

        $scores = (new CentralityService())->betweennessCentrality($g);

        expect($scores['Y'])->toBe(1.0)
            ->and($scores['X'])->toBe(0.0)
            ->and($scores['Z'])->toBe(0.0);
    });

    it('splits credit across equally short paths', function () {
        // 4-cycle A-B-C-D-A: opposite corners have two equal shortest paths,
        // so each intermediary carries half. By symmetry every node = 1/6.
        $g = new Graph();
        $g->addEdge('A', 'B');
        $g->addEdge('B', 'C');
        $g->addEdge('C', 'D');
        $g->addEdge('D', 'A');

        $scores = (new CentralityService())->betweennessCentrality($g);

        expect($scores['A'])->toEqualWithDelta(1 / 6, 1e-9)
            ->and($scores['B'])->toEqualWithDelta(1 / 6, 1e-9)
            ->and($scores['C'])->toEqualWithDelta(1 / 6, 1e-9)
            ->and($scores['D'])->toEqualWithDelta(1 / 6, 1e-9);
    });

    it('scores every node 0.0 when no node can be an intermediary', function () {
        // n = 2 → (n-1)(n-2) = 0; the guard must avoid a division by zero.
        $g = new Graph();
        $g->addEdge('A', 'B');

        $scores = (new CentralityService())->betweennessCentrality($g);

        expect($scores)->toBe(['A' => 0.0, 'B' => 0.0]);
    });

    it('returns an empty result for an empty graph', function () {
        expect((new CentralityService())->betweennessCentrality(new Graph()))->toBe([]);
    });
});

describe('CentralityService::pageRankCentrality', function () {
    it('distributes rank uniformly on a symmetric triangle', function () {
        // Every node is structurally identical → equal PageRank = 1/3.
        $g = new Graph();
        $g->addEdge('A', 'B');
        $g->addEdge('B', 'C');
        $g->addEdge('C', 'A');

        $scores = (new CentralityService())->pageRankCentrality($g);

        expect($scores['A'])->toEqualWithDelta(1 / 3, 1e-6)
            ->and($scores['B'])->toEqualWithDelta(1 / 3, 1e-6)
            ->and($scores['C'])->toEqualWithDelta(1 / 3, 1e-6);
    });

    it('forms a probability distribution that sums to 1', function () {
        // Holds even with the dangling isolated node E, thanks to the
        // uniform redistribution of dangling mass.
        $scores = (new CentralityService())->pageRankCentrality(fixtureGraph());

        expect(array_sum($scores))->toEqualWithDelta(1.0, 1e-6);
    });

    it('ranks the most-connected node highest', function () {
        $scores = (new CentralityService())->pageRankCentrality(fixtureGraph());

        arsort($scores);
        expect(array_key_first($scores))->toBe('A');
    });

    it('splits rank evenly between two mutually linked nodes', function () {
        $g = new Graph();
        $g->addEdge('A', 'B');

        $scores = (new CentralityService())->pageRankCentrality($g);

        expect($scores['A'])->toEqualWithDelta(0.5, 1e-6)
            ->and($scores['B'])->toEqualWithDelta(0.5, 1e-6);
    });

    it('assigns all rank to the sole node of a single-node graph', function () {
        $g = new Graph();
        $g->addNode('only');

        $scores = (new CentralityService())->pageRankCentrality($g);

        expect($scores['only'])->toEqualWithDelta(1.0, 1e-6);
    });

    it('returns an empty result for an empty graph', function () {
        expect((new CentralityService())->pageRankCentrality(new Graph()))->toBe([]);
    });
});
