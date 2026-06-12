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
