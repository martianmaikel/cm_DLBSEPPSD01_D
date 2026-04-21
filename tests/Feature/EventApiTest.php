<?php

use App\Models\Event;
use App\Models\Source;
use App\Models\SourceFamily;
use Tests\Support\CreatesSqliteSchema;

uses(Tests\TestCase::class, CreatesSqliteSchema::class);

beforeEach(function () {
    $this->createTestSchema();
});

afterEach(function () {
    $this->dropTestSchema();
});

function makeApiSource(): Source
{
    $family = SourceFamily::create(['name' => 'API Test Family ' . uniqid()]);
    return Source::create([
        'name' => 'API Test Source',
        'type' => 'rss',
        'url' => 'https://example.com/feed',
        'source_family_id' => $family->id,
        'polling_interval' => 10,
        'active' => true,
    ]);
}

function makeApiEvent(Source $source, array $overrides = []): Event
{
    return Event::create(array_merge([
        'title' => 'Test event',
        'raw_content' => 'Test raw content.',
        'summary' => 'Test summary.',
        'category' => 'airstrike',
        'severity' => 5,
        'confidence' => 5,
        'status' => 'unverified',
        'country' => 'UA',
        'region' => 'Kyiv Oblast',
        'geo_approximate' => true,
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5(uniqid()),
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ], $overrides));
}

test('GET /api/events returns paginated JSON response', function () {
    $source = makeApiSource();
    makeApiEvent($source);
    makeApiEvent($source, ['hash' => md5(uniqid()), 'title' => 'Second event']);

    $response = $this->getJson('/api/events');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'category', 'severity', 'status', 'country'],
            ],
            'current_page',
            'per_page',
            'total',
        ]);
});

test('GET /api/events returns all events when no filters applied', function () {
    $source = makeApiSource();
    makeApiEvent($source);
    makeApiEvent($source, ['hash' => md5(uniqid())]);
    makeApiEvent($source, ['hash' => md5(uniqid())]);

    $response = $this->getJson('/api/events');

    $response->assertOk()
        ->assertJsonPath('total', 3);
});

test('GET /api/events filters by country', function () {
    $source = makeApiSource();
    makeApiEvent($source, ['country' => 'UA']);
    makeApiEvent($source, ['hash' => md5(uniqid()), 'country' => 'RU']);
    makeApiEvent($source, ['hash' => md5(uniqid()), 'country' => 'UA']);

    $response = $this->getJson('/api/events?country=UA');

    $response->assertOk()
        ->assertJsonPath('total', 2);

    $countries = collect($response->json('data'))->pluck('country');
    expect($countries->unique()->values()->all())->toBe(['UA']);
});

test('GET /api/events filters by category', function () {
    $source = makeApiSource();
    makeApiEvent($source, ['category' => 'airstrike']);
    makeApiEvent($source, ['hash' => md5(uniqid()), 'category' => 'artillery']);
    makeApiEvent($source, ['hash' => md5(uniqid()), 'category' => 'airstrike']);

    $response = $this->getJson('/api/events?category=airstrike');

    $response->assertOk()
        ->assertJsonPath('total', 2);

    $categories = collect($response->json('data'))->pluck('category');
    expect($categories->unique()->values()->all())->toBe(['airstrike']);
});

test('GET /api/events filters by status', function () {
    $source = makeApiSource();
    makeApiEvent($source, ['status' => 'unverified']);
    makeApiEvent($source, ['hash' => md5(uniqid()), 'status' => 'corroborated']);
    makeApiEvent($source, ['hash' => md5(uniqid()), 'status' => 'unverified']);

    $response = $this->getJson('/api/events?status=corroborated');

    $response->assertOk()
        ->assertJsonPath('total', 1);

    $statuses = collect($response->json('data'))->pluck('status');
    expect($statuses->first())->toBe('corroborated');
});

test('GET /api/events filters by severity_min', function () {
    $source = makeApiSource();
    makeApiEvent($source, ['severity' => 3]);
    makeApiEvent($source, ['hash' => md5(uniqid()), 'severity' => 7]);
    makeApiEvent($source, ['hash' => md5(uniqid()), 'severity' => 9]);

    $response = $this->getJson('/api/events?severity_min=7');

    $response->assertOk()
        ->assertJsonPath('total', 2);
});

test('GET /api/events filters by severity_max', function () {
    $source = makeApiSource();
    makeApiEvent($source, ['severity' => 3]);
    makeApiEvent($source, ['hash' => md5(uniqid()), 'severity' => 7]);
    makeApiEvent($source, ['hash' => md5(uniqid()), 'severity' => 9]);

    $response = $this->getJson('/api/events?severity_max=7');

    $response->assertOk()
        ->assertJsonPath('total', 2);
});

test('GET /api/events/{event} returns full event with corroboration chain', function () {
    $source = makeApiSource();
    $event = makeApiEvent($source);

    $response = $this->getJson("/api/events/{$event->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'event' => ['id', 'title', 'category', 'severity', 'confidence', 'status', 'country'],
            'corroboration_chain',
        ])
        ->assertJsonPath('event.id', $event->id);
});

test('GET /api/events/{event} returns 404 for nonexistent event', function () {
    $response = $this->getJson('/api/events/00000000-0000-0000-0000-000000000000');

    $response->assertNotFound();
});

test('GET /api/events returns empty data array when no events exist', function () {
    $response = $this->getJson('/api/events');

    $response->assertOk()
        ->assertJsonPath('total', 0)
        ->assertJsonPath('data', []);
});

test('GET /api/events returns events ordered by occurred_at descending', function () {
    $source = makeApiSource();
    makeApiEvent($source, ['occurred_at' => now()->subHours(5), 'hash' => md5('old')]);
    makeApiEvent($source, ['occurred_at' => now()->subHour(), 'hash' => md5('mid')]);
    makeApiEvent($source, ['occurred_at' => now(), 'hash' => md5('new')]);

    $response = $this->getJson('/api/events');

    $response->assertOk();

    $data = $response->json('data');
    // First item should be the most recent
    expect($data[0]['occurred_at'])->toBeGreaterThanOrEqual($data[1]['occurred_at'])
        ->and($data[1]['occurred_at'])->toBeGreaterThanOrEqual($data[2]['occurred_at']);
});
