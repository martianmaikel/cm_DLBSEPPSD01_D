<?php

namespace App\Contracts;

use App\DataTransferObjects\ThreatLevelBilingualResult;

interface ThreatLevelProvider
{
    public function generateBilingualSummary(array $context): ThreatLevelBilingualResult;
}
