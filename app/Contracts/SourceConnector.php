<?php

namespace App\Contracts;

use App\Models\Source;

interface SourceConnector
{
    /**
     * Poll the source for new items and dispatch processing jobs.
     */
    public function poll(Source $source): void;

    /**
     * Whether this connector supports the given source.
     */
    public function supports(Source $source): bool;
}
