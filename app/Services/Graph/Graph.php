<?php

namespace App\Services\Graph;

/**
 * Pure, framework-agnostic graph representation backed by an adjacency list.
 *
 * Deliberately decoupled from Eloquent and from {@see \App\DataTransferObjects\GraphResult}
 * so the centrality algorithms can be unit-tested against small, hand-verifiable
 * fixtures without touching the database. An adapter converts the heavier
 * GraphResult into this lean structure for production use.
 */
class Graph
{
    /**
     * Set of node ids. Value is always true; the key is the node id.
     *
     * @var array<string, true>
     */
    private array $nodes = [];

    /**
     * Adjacency list: source id => [neighbour id => edge weight].
     *
     * @var array<string, array<string, float>>
     */
    private array $adjacency = [];

    public function __construct(private readonly bool $directed = false) {}

    public function addNode(string $id): void
    {
        $this->nodes[$id] ??= true;
        $this->adjacency[$id] ??= [];
    }

    /**
     * Add an edge between two nodes, creating the nodes if needed.
     * In an undirected graph the reverse edge is added automatically.
     */
    public function addEdge(string $from, string $to, float $weight = 1.0): void
    {
        $this->addNode($from);
        $this->addNode($to);

        $this->adjacency[$from][$to] = $weight;
        if (! $this->directed) {
            $this->adjacency[$to][$from] = $weight;
        }
    }

    /**
     * @return array<int, string>
     */
    public function nodes(): array
    {
        return array_keys($this->nodes);
    }

    public function nodeCount(): int
    {
        return count($this->nodes);
    }

    public function hasNode(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    public function isDirected(): bool
    {
        return $this->directed;
    }

    /**
     * Neighbours of a node as [neighbour id => edge weight].
     *
     * @return array<string, float>
     */
    public function neighbors(string $id): array
    {
        return $this->adjacency[$id] ?? [];
    }

    /**
     * Number of distinct neighbours of a node.
     */
    public function degree(string $id): int
    {
        return count($this->adjacency[$id] ?? []);
    }
}
