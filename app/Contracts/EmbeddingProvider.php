<?php

namespace App\Contracts;

use App\DataTransferObjects\EmbeddingResult;

interface EmbeddingProvider
{
    public function generateEmbedding(string $text): EmbeddingResult;

    /**
     * Generate embeddings for multiple texts in a single API call.
     *
     * @param  string[]  $texts
     * @return EmbeddingResult[]  Results in the same order as input texts.
     */
    public function generateBatchEmbeddings(array $texts): array;

    public function getDimensions(): int;

    public function getProviderName(): string;
}
