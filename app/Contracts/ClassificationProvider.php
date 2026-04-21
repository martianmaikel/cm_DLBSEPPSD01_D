<?php

namespace App\Contracts;

use App\DataTransferObjects\ClassificationResult;

interface ClassificationProvider
{
    public function classify(string $rawContent, string $sourceContext): ClassificationResult;
}
