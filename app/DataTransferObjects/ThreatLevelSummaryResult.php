<?php

namespace App\DataTransferObjects;

class ThreatLevelSummaryResult
{
    public function __construct(
        public readonly string $label,
        public readonly string $summary,
    ) {}
}
