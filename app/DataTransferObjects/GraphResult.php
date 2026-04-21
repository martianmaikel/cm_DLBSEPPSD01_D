<?php

namespace App\DataTransferObjects;

class GraphResult
{
    /**
     * @param  array<string, array<string, mixed>>  $nodes  Keyed by node ID (type:id) for automatic dedup
     * @param  array<int, array<string, mixed>>    $edges  Deduped edges
     */
    public function __construct(
        public array $nodes = [],
        public array $edges = [],
    ) {}

    public function addNode(array $node): void
    {
        if (! isset($node['id'])) {
            return;
        }
        $id = $node['id'];
        if (! isset($this->nodes[$id])) {
            $this->nodes[$id] = $node;
        } else {
            // Merge in any new non-null fields (useful when same node is discovered via multiple paths)
            $this->nodes[$id] = array_merge(
                $this->nodes[$id],
                array_filter($node, fn ($v) => $v !== null)
            );
        }
    }

    public function addEdge(array $edge): void
    {
        $this->edges[] = $edge;
    }

    public function toArray(): array
    {
        return [
            'nodes' => array_values($this->nodes),
            'edges' => $this->edges,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function nodeIds(): array
    {
        return array_keys($this->nodes);
    }
}
