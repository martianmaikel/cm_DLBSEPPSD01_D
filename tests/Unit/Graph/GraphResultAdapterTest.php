<?php

use App\DataTransferObjects\GraphResult;
use App\Services\Graph\GraphResultAdapter;

/**
 * Triangle of three actors (all undirected):
 *   actor:1 — actor:2,  actor:1 — actor:3,  actor:2 — actor:3
 * Every node ends up with degree 2.
 */
function sampleResult(): GraphResult
{
    $result = new GraphResult();
    foreach (['actor:1', 'actor:2', 'actor:3'] as $id) {
        $result->addNode(['id' => $id, 'type' => 'actor', 'label' => $id]);
    }
    $result->addEdge(['from' => 'actor:1', 'to' => 'actor:2', 'weight' => null]);
    $result->addEdge(['from' => 'actor:1', 'to' => 'actor:3', 'weight' => 0.5]);
    $result->addEdge(['from' => 'actor:2', 'to' => 'actor:3', 'weight' => null]);

    return $result;
}

describe('GraphResultAdapter::toGraph', function () {
    it('maps nodes and edges into an undirected Graph', function () {
        $graph = (new GraphResultAdapter())->toGraph(sampleResult());

        expect($graph->nodeCount())->toBe(3)
            ->and($graph->degree('actor:1'))->toBe(2)
            ->and($graph->degree('actor:2'))->toBe(2)
            ->and($graph->degree('actor:3'))->toBe(2);
    });

    it('collapses parallel edges between the same pair', function () {
        $result = sampleResult();
        $result->addEdge(['from' => 'actor:1', 'to' => 'actor:2']); // duplicate

        $graph = (new GraphResultAdapter())->toGraph($result);

        // Simple graph: actor:1 keeps degree 2, not 3.
        expect($graph->degree('actor:1'))->toBe(2);
    });

    it('skips edges referencing a node outside the declared set', function () {
        $result = sampleResult();
        $result->addEdge(['from' => 'actor:1', 'to' => 'actor:999']); // phantom endpoint

        $graph = (new GraphResultAdapter())->toGraph($result);

        expect($graph->hasNode('actor:999'))->toBeFalse()
            ->and($graph->degree('actor:1'))->toBe(2);
    });

    it('ignores self-loops', function () {
        $result = sampleResult();
        $result->addEdge(['from' => 'actor:1', 'to' => 'actor:1']);

        $graph = (new GraphResultAdapter())->toGraph($result);

        expect($graph->degree('actor:1'))->toBe(2);
    });

    it('returns an empty Graph for an empty result', function () {
        $graph = (new GraphResultAdapter())->toGraph(new GraphResult());

        expect($graph->nodeCount())->toBe(0)
            ->and($graph->nodes())->toBe([]);
    });

    it('keeps edge direction when asked for a directed Graph', function () {
        $result = new GraphResult();
        $result->addNode(['id' => 'a']);
        $result->addNode(['id' => 'b']);
        $result->addEdge(['from' => 'a', 'to' => 'b']);

        $graph = (new GraphResultAdapter())->toGraph($result, directed: true);

        // a → b only; b has no outgoing edge.
        expect($graph->degree('a'))->toBe(1)
            ->and($graph->degree('b'))->toBe(0);
    });
});
