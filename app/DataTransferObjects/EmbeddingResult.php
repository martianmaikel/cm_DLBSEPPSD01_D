<?php

namespace App\DataTransferObjects;

class EmbeddingResult
{
    public function __construct(
        public readonly array $vector,
        public readonly int $dimensions,
        public readonly string $provider,
    ) {}
}
