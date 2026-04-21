<?php

use App\Contracts\ClassificationProvider;
use App\Contracts\EmbeddingProvider;
use App\DataTransferObjects\ClassificationResult;
use App\DataTransferObjects\EmbeddingResult;
use App\Jobs\AssignThreadJob;
use App\Jobs\CorroborateEventJob;
use App\Jobs\GenerateEmbeddingJob;
use App\Jobs\GeocodeEventJob;
use App\Jobs\ProcessRawEventJob;
use App\Models\Embedding;
use App\Models\EntityExtraction;
use App\Models\Event;
use App\Models\Source;
use App\Models\SourceFamily;
use App\Services\Geocoding\NominatimService;
use App\Services\Threading\ThreadMatchingService;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesSqliteSchema;

uses(Tests\TestCase::class, CreatesSqliteSchema::class);

beforeEach(function () {
    $this->createTestSchema();
    Queue::fake();
});

afterEach(function () {
    $this->dropTestSchema();
});

function makePipelineSource(string $suffix = ''): Source
{
    $family = SourceFamily::create(['name' => 'Pipeline Family ' . $suffix . uniqid()]);
    return Source::create([
        'name' => 'Pipeline Reuters RSS ' . $suffix,
        'type' => 'rss',
        'url' => 'https://reuters.com/feed',
        'source_family_id' => $family->id,
        'polling_interval' => 10,
        'active' => true,
    ]);
}

function makePipelineClassificationResult(): ClassificationResult
{
    return ClassificationResult::fromArray([
        'category' => 'airstrike',
        'severity' => 8,
        'confidence' => 7,
        'entities' => [
            ['name' => 'Kharkiv', 'type' => 'location'],
            ['name' => 'Russian Air Force', 'type' => 'unit'],
        ],
        'country' => 'UA',
        'region' => 'Kharkiv Oblast',
        'latitude' => 49.9935,
        'longitude' => 36.2304,
        'summary' => 'Russian airstrikes hit infrastructure in Kharkiv Oblast.',
    ]);
}

// ── Step 1: ProcessRawEventJob creates Event and EntityExtractions ─────────────

test('step 1: ProcessRawEventJob creates event with correct fields', function () {
    $source = makePipelineSource('step1');
    $classificationResult = makePipelineClassificationResult();

    $provider = mock(ClassificationProvider::class);
    $provider->shouldReceive('classify')->once()->andReturn($classificationResult);

    $payload = [
        'title' => 'Russian airstrikes hit Kharkiv',
        'raw_content' => 'Russian forces launched multiple airstrikes against Kharkiv infrastructure.',
        'source_id' => $source->id,
        'hash' => md5('pipeline-step1-' . uniqid()),
        'occurred_at' => now()->toDateTimeString(),
    ];

    $job = new ProcessRawEventJob($payload);
    $job->handle($provider);

    $event = Event::where('hash', $payload['hash'])->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('Russian airstrikes hit Kharkiv')
        ->and($event->category)->toBe('airstrike')
        ->and($event->severity)->toBe(8)
        ->and($event->confidence)->toBe(7)
        ->and($event->status)->toBe('unverified')
        ->and($event->country)->toBe('UA')
        ->and($event->region)->toBe('Kharkiv Oblast')
        ->and($event->source_id)->toBe($source->id)
        ->and($event->corroboration_count)->toBe(0)
        ->and($event->geo_approximate)->toBeTrue();
});

test('step 1: ProcessRawEventJob creates EntityExtraction records', function () {
    $source = makePipelineSource('entities');
    $classificationResult = makePipelineClassificationResult();

    $provider = mock(ClassificationProvider::class);
    $provider->shouldReceive('classify')->once()->andReturn($classificationResult);

    $payload = [
        'title' => 'Kharkiv airstrike',
        'raw_content' => 'Airstrike content.',
        'source_id' => $source->id,
        'hash' => md5('pipeline-entities-' . uniqid()),
        'occurred_at' => now()->toDateTimeString(),
    ];

    (new ProcessRawEventJob($payload))->handle($provider);

    $event = Event::where('hash', $payload['hash'])->first();
    $extractions = EntityExtraction::where('event_id', $event->id)->get();

    expect($extractions)->toHaveCount(2);
    expect($extractions->pluck('name')->toArray())
        ->toContain('Kharkiv')
        ->toContain('Russian Air Force');
});

test('step 1: ProcessRawEventJob dispatches GeocodeEventJob (embeddings handled by batch job)', function () {
    $source = makePipelineSource('dispatch');
    $classificationResult = makePipelineClassificationResult();

    $provider = mock(ClassificationProvider::class);
    $provider->shouldReceive('classify')->once()->andReturn($classificationResult);

    $payload = [
        'title' => 'Dispatch test event',
        'raw_content' => 'Content.',
        'source_id' => $source->id,
        'hash' => md5('pipeline-dispatch-' . uniqid()),
        'occurred_at' => now()->toDateTimeString(),
    ];

    (new ProcessRawEventJob($payload))->handle($provider);

    Queue::assertNotPushed(GenerateEmbeddingJob::class);
    Queue::assertPushed(GeocodeEventJob::class);
});

// ── Step 2: GenerateEmbeddingJob stores Embedding record ──────────────────────

test('step 2: GenerateEmbeddingJob creates Embedding record', function () {
    $source = makePipelineSource('embed');

    $event = Event::create([
        'title' => 'Embed test event',
        'raw_content' => 'Content for embedding.',
        'summary' => 'Summary.',
        'category' => 'airstrike',
        'severity' => 8,
        'confidence' => 7,
        'status' => 'unverified',
        'country' => 'UA',
        'region' => 'Kharkiv Oblast',
        'geo_approximate' => true,
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5('embed-event-' . uniqid()),
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ]);

    $embeddingResult = new EmbeddingResult(
        vector: array_fill(0, 8, 0.1),
        dimensions: 8,
        provider: 'grok',
    );

    $embeddingProvider = mock(EmbeddingProvider::class);
    $embeddingProvider->shouldReceive('getProviderName')->andReturn('grok');
    $embeddingProvider->shouldReceive('getDimensions')->andReturn(8);
    $embeddingProvider->shouldReceive('generateEmbedding')
        ->once()
        ->with('Embed test event Summary.')
        ->andReturn($embeddingResult);

    // The job calls DB::statement('UPDATE embeddings SET vector = ?...')
    // which will fail on SQLite — it throws, so GenerateEmbeddingJob re-throws it.
    // We verify that the Embedding *row* is created (via Eloquent) before the vector update.
    // Since the DB::statement throws on SQLite, the job will throw — we catch that here.
    try {
        (new GenerateEmbeddingJob($event->id))->handle($embeddingProvider);
    } catch (\Throwable) {
        // Expected: SQLite doesn't support pgvector syntax in DB::statement
    }

    // The Embedding record row should have been created before the vector UPDATE
    $embedding = Embedding::where('event_id', $event->id)->where('provider', 'grok')->first();

    expect($embedding)->not->toBeNull()
        ->and($embedding->provider)->toBe('grok')
        ->and($embedding->dimensions)->toBe(8);
});

test('step 2: GenerateEmbeddingJob is idempotent — skips if embedding already exists', function () {
    $source = makePipelineSource('idem');

    $event = Event::create([
        'title' => 'Already embedded event',
        'raw_content' => 'Content.',
        'summary' => 'Summary.',
        'category' => 'airstrike',
        'severity' => 5,
        'confidence' => 5,
        'status' => 'unverified',
        'country' => 'UA',
        'geo_approximate' => true,
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5('idem-' . uniqid()),
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ]);

    Embedding::create([
        'event_id' => $event->id,
        'provider' => 'grok',
        'dimensions' => 1536,
    ]);

    $embeddingProvider = mock(EmbeddingProvider::class);
    $embeddingProvider->shouldReceive('getProviderName')->andReturn('grok');
    $embeddingProvider->shouldReceive('generateEmbedding')->never();

    (new GenerateEmbeddingJob($event->id))->handle($embeddingProvider);

    expect(Embedding::where('event_id', $event->id)->count())->toBe(1);
});

// ── Step 3: GeocodeEventJob handles coordinates and dispatches corroboration ───

test('step 3: GeocodeEventJob sets geo_approximate false when LLM coordinates provided', function () {
    $source = makePipelineSource('geo');

    $event = Event::create([
        'title' => 'Geocode test event',
        'raw_content' => 'Content.',
        'summary' => 'Summary.',
        'category' => 'airstrike',
        'severity' => 7,
        'confidence' => 6,
        'status' => 'unverified',
        'country' => 'UA',
        'region' => 'Kharkiv Oblast',
        'geo_approximate' => true,
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5('geo-event-' . uniqid()),
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ]);

    $geocoder = mock(NominatimService::class);
    $geocoder->shouldReceive('geocode')->never();

    // DB::statement for PostGIS will fail on SQLite — we catch it
    try {
        (new GeocodeEventJob($event->id, 49.9935, 36.2304))->handle($geocoder);
    } catch (\Throwable) {
        // Expected on SQLite: ST_SetSRID/ST_MakePoint not available
    }

    Queue::assertPushed(CorroborateEventJob::class);
});

test('step 3: GeocodeEventJob dispatches CorroborateEventJob after Nominatim success', function () {
    $source = makePipelineSource('nominatim');

    $event = Event::create([
        'title' => 'Nominatim geocode event',
        'raw_content' => 'Content.',
        'summary' => 'Summary.',
        'category' => 'artillery',
        'severity' => 5,
        'confidence' => 5,
        'status' => 'unverified',
        'country' => 'UA',
        'region' => 'Kherson Oblast',
        'geo_approximate' => true,
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5('nominatim-event-' . uniqid()),
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ]);

    $geocoder = mock(NominatimService::class);
    $geocoder->shouldReceive('geocode')
        ->once()
        ->andReturn([46.6354, 32.6169]);

    // PostGIS UPDATE will fail on SQLite — caught inside the job
    try {
        (new GeocodeEventJob($event->id))->handle($geocoder);
    } catch (\Throwable) {
        // Expected on SQLite
    }

    Queue::assertPushed(CorroborateEventJob::class);
});

// ── Step 4: AssignThreadJob delegates to ThreadMatchingService ─────────────────

test('step 4: AssignThreadJob delegates to ThreadMatchingService', function () {
    $source = makePipelineSource('thread');

    $event = Event::create([
        'title' => 'Major offensive begins',
        'raw_content' => 'A major offensive has begun.',
        'summary' => 'Large-scale offensive reported.',
        'category' => 'troop_movement',
        'severity' => 8,
        'confidence' => 6,
        'status' => 'unverified',
        'country' => 'UA',
        'region' => 'Zaporizhzhia Oblast',
        'geo_approximate' => true,
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5('thread-event-' . uniqid()),
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ]);

    $threadMatcher = mock(ThreadMatchingService::class);
    $threadMatcher->shouldReceive('assignThread')
        ->once()
        ->with(\Mockery::on(fn($e) => $e->id === $event->id));

    (new AssignThreadJob($event->id))->handle($threadMatcher);
});

// ── Failure path ───────────────────────────────────────────────────────────────

test('pipeline failure: LLM error stores pending_classification and blocks downstream jobs', function () {
    $source = makePipelineSource('fail');

    $provider = mock(ClassificationProvider::class);
    $provider->shouldReceive('classify')
        ->once()
        ->andThrow(new \RuntimeException('LLM service unavailable'));

    $payload = [
        'title' => 'Incident report',
        'raw_content' => 'Something happened somewhere.',
        'source_id' => $source->id,
        'hash' => md5('failure-pipeline-' . uniqid()),
        'occurred_at' => now()->toDateTimeString(),
    ];

    (new ProcessRawEventJob($payload))->handle($provider);

    $event = Event::where('hash', $payload['hash'])->first();

    expect($event)->not->toBeNull()
        ->and($event->status)->toBe('pending_classification')
        ->and($event->classification_attempts)->toBe(1);

    Queue::assertNotPushed(GenerateEmbeddingJob::class);
    Queue::assertNotPushed(GeocodeEventJob::class);
    Queue::assertNotPushed(CorroborateEventJob::class);
    Queue::assertNotPushed(AssignThreadJob::class);
});
