<?php

namespace App\DataTransferObjects;

class BilingualBriefingResult
{
    public function __construct(
        public readonly string $titleEn,
        public readonly string $titleDe,
        public readonly string $summaryEn,
        public readonly string $summaryDe,
        public readonly array $keyDevelopmentsEn,
        public readonly array $keyDevelopmentsDe,
        public readonly array $conflictSectionsEn,
        public readonly array $conflictSectionsDe,
        public readonly array $statistics,
    ) {}
}
