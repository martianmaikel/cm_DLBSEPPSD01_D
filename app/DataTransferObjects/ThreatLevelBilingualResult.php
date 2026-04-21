<?php

namespace App\DataTransferObjects;

class ThreatLevelBilingualResult
{
    public function __construct(
        public readonly string $labelEn,
        public readonly string $labelDe,
        public readonly string $summaryEn,
        public readonly string $summaryDe,
    ) {}
}
