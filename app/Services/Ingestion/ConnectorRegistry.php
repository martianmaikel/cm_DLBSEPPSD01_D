<?php

namespace App\Services\Ingestion;

use App\Contracts\SourceConnector;
use App\Models\Source;
use RuntimeException;

class ConnectorRegistry
{
    /** @var array<string, SourceConnector> type => connector */
    private array $connectors = [];

    /** @var array<string, SourceConnector> FQCN => connector */
    private array $classConnectors = [];

    public function register(string $type, SourceConnector $connector): void
    {
        $this->connectors[$type] = $connector;
    }

    public function registerClass(string $className, SourceConnector $connector): void
    {
        $this->classConnectors[$className] = $connector;
    }

    public function resolve(Source $source): SourceConnector
    {
        // Priority 1: explicit connector_class on the source
        if ($source->connector_class && isset($this->classConnectors[$source->connector_class])) {
            return $this->classConnectors[$source->connector_class];
        }

        // Priority 2: match by source type
        if (isset($this->connectors[$source->type])) {
            return $this->connectors[$source->type];
        }

        throw new RuntimeException("No connector registered for source type '{$source->type}' (source #{$source->id}: {$source->name})");
    }

    public function has(Source $source): bool
    {
        if ($source->connector_class && isset($this->classConnectors[$source->connector_class])) {
            return true;
        }

        return isset($this->connectors[$source->type]);
    }

    /** @return array<string> */
    public function registeredTypes(): array
    {
        return array_keys($this->connectors);
    }
}
