<?php

use App\Contracts\ClassificationProvider;
use App\DataTransferObjects\ClassificationResult;
use App\Jobs\GenerateEmbeddingJob;
use App\Jobs\GeocodeEventJob;
use App\Jobs\ProcessRawEventJob;
use App\Models\EntityExtraction;
use App\Models\Event;
use App\Models\Source;
use App\Models\SourceFamily;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesSqliteSchema;

uses(Tests\TestCase::class, CreatesSqliteSchema::class);

beforeEach(function () {
    $this->createTestSchema();
});

afterEach(function () {
    $this->dropTestSchema();
});

function makeSource(?int $familyId = null): Source
{
    if ($familyId === null) {
        $family = SourceFamily::create(['name' => 'Test Family ' . uniqid()]);
        $familyId = $family->id;
    }

    return Source::create([
        'name' => 'Test Source',
        'type' => 'rss',
        'url' => 'https://example.com/feed',
        'source_family_id' => $familyId,
        'polling_interval' => 10,
        'active' => true,
    ]);
}

function makePayload(Source $source, array $overrides = []): array
{
    return array_merge([
        'title' => 'Explosion reported near airport',
        'raw_content' => 'A large explosion was reported near the main airport.',
        'source_id' => $source->id,
        'hash' => md5(uniqid()),
        'occurred_at' => now()->toDateTimeString(),
    ], $overrides);
}

function makeClassificationResult(array $overrides = []): ClassificationResult
{
    return ClassificationResult::fromArray(array_merge([
        'category' => 'airstrike',
        'severity' => 7,
        'confidence' => 6,
        'entities' => [
            ['name' => 'Kyiv Airport', 'type' => 'location'],
            ['name' => '36th Air Brigade', 'type' => 'unit'],
        ],
        'country' => 'UA',
        'region' => 'Kyiv Oblast',
        'latitude' => 50.4501,
        'longitude' => 30.5234,
        'summary' => 'An explosion was reported near Kyiv airport.',
    ], $overrides));
}

test('ProcessRawEventJob creates an Event with correct fields', function () {
    Queue::fake();

    $source = makeSource();
    $payload = makePayload($source);
    $classificationResult = makeClassificationResult();

    $provider = mock(ClassificationProvider::class);
    $provider->shouldReceive('classify')
        ->once()
        ->andReturn($classificationResult);

    $job = new ProcessRawEventJob($payload);
    $job->handle($provider);

    $event = Event::where('hash', $payload['hash'])->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe($payload['title'])
        ->and($event->summary)->toBe($classificationResult->summary)
        ->and($event->category)->toBe('airstrike')
        ->and($event->severity)->toBe(7)
        ->and($event->confidence)->toBe(6)
        ->and($event->status)->toBe('unverified')
        ->and($event->country)->toBe('UA')
        ->and($event->region)->toBe('Kyiv Oblast')
        ->and($event->source_id)->toBe($source->id)
        ->and($event->corroboration_count)->toBe(0)
        ->and($event->classification_attempts)->toBe(1)
        ->and($event->geo_approximate)->toBeTrue(); // always true until geocoded
});

test('ProcessRawEventJob creates EntityExtraction records', function () {
    Queue::fake();

    $source = makeSource();
    $payload = makePayload($source);
    $classificationResult = makeClassificationResult();

    $provider = mock(ClassificationProvider::class);
    $provider->shouldReceive('classify')->once()->andReturn($classificationResult);

    $job = new ProcessRawEventJob($payload);
    $job->handle($provider);

    $event = Event::where('hash', $payload['hash'])->first();
    $extractions = EntityExtraction::where('event_id', $event->id)->get();

    expect($extractions)->toHaveCount(2);

    $names = $extractions->pluck('name')->toArray();
    expect($names)->toContain('Kyiv Airport')
        ->toContain('36th Air Brigade');

    $types = $extractions->pluck('type')->toArray();
    expect($types)->toContain('location')
        ->toContain('unit');
});

test('ProcessRawEventJob does not dispatch GenerateEmbeddingJob (handled by batch job)', function () {
    Queue::fake();

    $source = makeSource();
    $payload = makePayload($source);

    $provider = mock(ClassificationProvider::class);
    $provider->shouldReceive('classify')->once()->andReturn(makeClassificationResult());

    $job = new ProcessRawEventJob($payload);
    $job->handle($provider);

    Queue::assertNotPushed(GenerateEmbeddingJob::class);
});

test('ProcessRawEventJob dispatches GeocodeEventJob after success', function () {
    Queue::fake();

    $source = makeSource();
    $payload = makePayload($source);

    $provider = mock(ClassificationProvider::class);
    $provider->shouldReceive('classify')->once()->andReturn(makeClassificationResult());

    $job = new ProcessRawEventJob($payload);
    $job->handle($provider);

    Queue::assertPushed(GeocodeEventJob::class);
});

test('ProcessRawEventJob stores pending_classification on classification failure', function () {
    Queue::fake();

    $source = makeSource();
    $payload = makePayload($source);

    $provider = mock(ClassificationProvider::class);
    $provider->shouldReceive('classify')
        ->once()
        ->andThrow(new \RuntimeException('LLM timeout'));

    $job = new ProcessRawEventJob($payload);
    $job->handle($provider);

    $event = Event::where('hash', $payload['hash'])->first();

    expect($event)->not->toBeNull()
        ->and($event->status)->toBe('pending_classification')
        ->and($event->classification_attempts)->toBe(1);
});

test('ProcessRawEventJob does not dispatch downstream jobs on failure', function () {
    Queue::fake();

    $source = makeSource();
    $payload = makePayload($source);

    $provider = mock(ClassificationProvider::class);
    $provider->shouldReceive('classify')
        ->once()
        ->andThrow(new \RuntimeException('API error'));

    $job = new ProcessRawEventJob($payload);
    $job->handle($provider);

    Queue::assertNotPushed(GenerateEmbeddingJob::class);
    Queue::assertNotPushed(GeocodeEventJob::class);
});

test('ProcessRawEventJob skips duplicate hash', function () {
    Queue::fake();

    $source = makeSource();
    $hash = md5('fixed-hash');
    $payload = makePayload($source, ['hash' => $hash]);

    // Create an existing event with the same hash
    Event::create([
        'title' => 'Existing event',
        'raw_content' => 'Already processed',
        'summary' => '',
        'category' => 'other',
        'severity' => 1,
        'confidence' => 1,
        'status' => 'unverified',
        'source_id' => $source->id,
        'hash' => $hash,
        'corroboration_count' => 0,
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ]);

    $provider = mock(ClassificationProvider::class);
    $provider->shouldReceive('classify')->never();

    $job = new ProcessRawEventJob($payload);
    $job->handle($provider);

    // Only one event with this hash should exist
    expect(Event::where('hash', $hash)->count())->toBe(1);
});

test('ProcessRawEventJob skips if source does not exist', function () {
    Queue::fake();

    $payload = [
        'title' => 'Orphaned event',
        'raw_content' => 'Content without valid source.',
        'source_id' => 99999,
        'hash' => md5(uniqid()),
        'occurred_at' => now()->toDateTimeString(),
    ];

    $provider = mock(ClassificationProvider::class);
    $provider->shouldReceive('classify')->never();

    $job = new ProcessRawEventJob($payload);
    $job->handle($provider);

    expect(Event::count())->toBe(0);
});

test('ProcessRawEventJob increments classification_attempts on repeated failure', function () {
    Queue::fake();

    $source = makeSource();
    $hash = md5('retry-hash');

    // Pre-create a pending_classification event
    Event::create([
        'title' => 'Failed before',
        'raw_content' => 'Old content',
        'summary' => '',
        'category' => 'other',
        'severity' => 1,
        'confidence' => 1,
        'status' => 'pending_classification',
        'source_id' => $source->id,
        'hash' => $hash,
        'corroboration_count' => 0,
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ]);

    $payload = makePayload($source, ['hash' => $hash]);

    $provider = mock(ClassificationProvider::class);
    $provider->shouldReceive('classify')
        ->once()
        ->andThrow(new \RuntimeException('Still failing'));

    $job = new ProcessRawEventJob($payload);
    $job->handle($provider);

    $event = Event::where('hash', $hash)->first();
    expect($event->classification_attempts)->toBe(2);
});
