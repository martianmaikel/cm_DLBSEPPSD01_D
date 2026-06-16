<?php

use App\DataTransferObjects\GraphResult;
use App\Services\Graph\GraphBuilderService;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Star-ish actor graph (undirected):
 *   actor:1 — actor:2, actor:1 — actor:3, actor:1 — actor:4, actor:2 — actor:3
 *
 * Degrees: actor:1 = 3 (hub), actor:2 = 2, actor:3 = 2, actor:4 = 1.
 * Freeman degree centrality of the hub = 3 / (4 - 1) = 1.0.
 */
beforeEach(function () {
    $result = new GraphResult();
    $result->addNode(['id' => 'actor:1', 'type' => 'actor', 'label' => 'Hub']);
    $result->addNode(['id' => 'actor:2', 'type' => 'actor', 'label' => 'Spoke A']);
    $result->addNode(['id' => 'actor:3', 'type' => 'actor', 'label' => 'Spoke B']);
    $result->addNode(['id' => 'actor:4', 'type' => 'actor', 'label' => 'Leaf']);
    $result->addEdge(['from' => 'actor:1', 'to' => 'actor:2']);
    $result->addEdge(['from' => 'actor:1', 'to' => 'actor:3']);
    $result->addEdge(['from' => 'actor:1', 'to' => 'actor:4']);
    $result->addEdge(['from' => 'actor:2', 'to' => 'actor:3']);

    $this->mock(GraphBuilderService::class, function ($mock) use ($result) {
        $mock->shouldReceive('globalSnapshot')->andReturn($result);
    });
});

it('ranks actors by degree centrality, descending', function () {
    $response = $this->getJson('/api/graph/centrality?metric=degree');

    $response->assertOk()
        ->assertJsonPath('metric', 'degree')
        ->assertJsonPath('count', 4)
        ->assertJsonPath('nodes.0.id', 'actor:1')   // hub ranks first
        ->assertJsonPath('nodes.0.label', 'Hub');

    $nodes = $response->json('nodes');
    expect($nodes[0]['degree'])->toEqualWithDelta(1.0, 1e-9)              // 3 / 3
        ->and($nodes[0]['degree'])->toBeGreaterThan($nodes[3]['degree']); // hub > leaf
});

it('returns all three metrics when asked', function () {
    $response = $this->getJson('/api/graph/centrality?metric=all');

    $response->assertOk()
        ->assertJsonPath('metric', 'all')
        ->assertJsonStructure([
            'metric',
            'count',
            'nodes' => [['id', 'label', 'type', 'degree', 'betweenness', 'pagerank']],
        ]);
});

it('rejects an unknown metric with 422', function () {
    $this->getJson('/api/graph/centrality?metric=bogus')->assertStatus(422);
});

it('honours the limit parameter', function () {
    $response = $this->getJson('/api/graph/centrality?metric=degree&limit=2');

    $response->assertOk()->assertJsonPath('count', 2);
    expect($response->json('nodes'))->toHaveCount(2);
});
