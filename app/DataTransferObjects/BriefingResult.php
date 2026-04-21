<?php

namespace App\DataTransferObjects;

class BriefingResult
{
    public function __construct(
        public readonly string $title,
        public readonly string $summary,
        public readonly array $keyDevelopments,
        public readonly array $statistics,
    ) {}
}
