<?php

use App\DataTransferObjects\EmbeddingResult;

test('EmbeddingResult stores vector, dimensions, and provider', function () {
    $vector = [0.1, 0.2, 0.3, -0.4, 0.5];
    $result = new EmbeddingResult(
        vector: $vector,
        dimensions: 5,
        provider: 'grok',
    );

    expect($result->vector)->toBe($vector)
        ->and($result->dimensions)->toBe(5)
        ->and($result->provider)->toBe('grok');
});

test('EmbeddingResult properties are readonly', function () {
    $result = new EmbeddingResult(
        vector: [0.1, 0.2],
        dimensions: 2,
        provider: 'gemini',
    );

    $reflection = new ReflectionClass($result);
    $props = $reflection->getProperties(ReflectionProperty::IS_READONLY);
    $names = array_map(fn($p) => $p->getName(), $props);

    expect($names)->toContain('vector')
        ->toContain('dimensions')
        ->toContain('provider');
});

test('EmbeddingResult dimensions matches vector length', function () {
    $vector = array_fill(0, 1536, 0.0);
    $result = new EmbeddingResult(
        vector: $vector,
        dimensions: 1536,
        provider: 'grok',
    );

    expect($result->dimensions)->toBe(count($result->vector));
});

test('EmbeddingResult accepts different provider names', function () {
    foreach (['grok', 'gemini', 'claude'] as $provider) {
        $result = new EmbeddingResult(vector: [0.1], dimensions: 1, provider: $provider);
        expect($result->provider)->toBe($provider);
    }
});

test('EmbeddingResult vector can contain negative values', function () {
    $vector = [0.5, -0.3, 1.0, -1.0, 0.0];
    $result = new EmbeddingResult(vector: $vector, dimensions: 5, provider: 'grok');

    expect($result->vector)->toBe($vector);
});
