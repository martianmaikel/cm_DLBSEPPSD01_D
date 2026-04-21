<?php

use App\DataTransferObjects\ClassificationResult;

test('fromArray() creates ClassificationResult with valid data', function () {
    $data = [
        'category' => 'airstrike',
        'severity' => 7,
        'confidence' => 8,
        'entities' => [
            ['name' => 'Kyiv', 'type' => 'location'],
            ['name' => '3rd Brigade', 'type' => 'unit'],
        ],
        'country' => 'UA',
        'region' => 'Kyiv Oblast',
        'latitude' => 50.4501,
        'longitude' => 30.5234,
        'summary' => 'An airstrike was reported over Kyiv.',
    ];

    $result = ClassificationResult::fromArray($data);

    expect($result->category)->toBe('airstrike')
        ->and($result->severity)->toBe(7)
        ->and($result->confidence)->toBe(8)
        ->and($result->entities)->toHaveCount(2)
        ->and($result->country)->toBe('UA')
        ->and($result->region)->toBe('Kyiv Oblast')
        ->and($result->latitude)->toBe(50.4501)
        ->and($result->longitude)->toBe(30.5234)
        ->and($result->summary)->toBe('An airstrike was reported over Kyiv.');
});

test('fromArray() clamps severity above 10 to 10', function () {
    $result = ClassificationResult::fromArray(['severity' => 15, 'confidence' => 5]);

    expect($result->severity)->toBe(10);
});

test('fromArray() clamps severity below 1 to 1', function () {
    $result = ClassificationResult::fromArray(['severity' => -3, 'confidence' => 5]);

    expect($result->severity)->toBe(1);
});

test('fromArray() clamps confidence above 10 to 10', function () {
    $result = ClassificationResult::fromArray(['severity' => 5, 'confidence' => 99]);

    expect($result->confidence)->toBe(10);
});

test('fromArray() clamps confidence below 1 to 1', function () {
    $result = ClassificationResult::fromArray(['severity' => 5, 'confidence' => 0]);

    expect($result->confidence)->toBe(1);
});

test('fromArray() applies default category when missing', function () {
    $result = ClassificationResult::fromArray([]);

    expect($result->category)->toBe('other');
});

test('fromArray() applies default severity of 5 when missing', function () {
    $result = ClassificationResult::fromArray([]);

    expect($result->severity)->toBe(5);
});

test('fromArray() applies default confidence of 1 when missing', function () {
    $result = ClassificationResult::fromArray([]);

    expect($result->confidence)->toBe(1);
});

test('fromArray() defaults entities to empty array when missing', function () {
    $result = ClassificationResult::fromArray([]);

    expect($result->entities)->toBe([]);
});

test('fromArray() defaults country and region to null when missing', function () {
    $result = ClassificationResult::fromArray([]);

    expect($result->country)->toBeNull()
        ->and($result->region)->toBeNull();
});

test('fromArray() defaults latitude and longitude to null when missing', function () {
    $result = ClassificationResult::fromArray([]);

    expect($result->latitude)->toBeNull()
        ->and($result->longitude)->toBeNull();
});

test('fromArray() defaults summary to empty string when missing', function () {
    $result = ClassificationResult::fromArray([]);

    expect($result->summary)->toBe('');
});

test('fromArray() casts severity to integer', function () {
    $result = ClassificationResult::fromArray(['severity' => '7', 'confidence' => '3']);

    expect($result->severity)->toBe(7)
        ->and($result->confidence)->toBe(3);
});

test('fromArray() casts latitude and longitude to float', function () {
    $result = ClassificationResult::fromArray([
        'latitude' => '48.8566',
        'longitude' => '2.3522',
    ]);

    expect($result->latitude)->toBe(48.8566)
        ->and($result->longitude)->toBe(2.3522);
});
