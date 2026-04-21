<?php

namespace App\Contracts;

use App\DataTransferObjects\BilingualBriefingResult;

interface BriefingProvider
{
    public function generateBilingualBriefing(array $eventSummaries, array $threadSummaries, array $comparisonContext = []): BilingualBriefingResult;
}
