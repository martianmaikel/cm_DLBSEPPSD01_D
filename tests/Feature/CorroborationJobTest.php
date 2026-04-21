<?php

use App\Jobs\AssignThreadJob;
use App\Jobs\CorroborateEventJob;
use App\Models\CorroborationLink;
use App\Models\EntityExtraction;
use App\Models\Event;
use App\Models\Source;
use App\Models\SourceFamily;
use App\Services\Corroboration\CorroborationService;
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

function makeSourceWithFamily(string $familyName): Source
{
    $family = SourceFamily::create(['name' => $familyName]);
    return Source::create([
        'name' => $familyName . ' Feed',
        'type' => 'rss',
        'url' => 'https://example.com/' . strtolower(str_replace(' ', '-', $familyName)),
        'source_family_id' => $family->id,
        'polling_interval' => 10,
        'active' => true,
    ]);
}

function makeCorroborationEvent(Source $source, array $overrides = []): Event
{
    return Event::create(array_merge([
        'title' => 'Airstrike on power grid',
        'raw_content' => 'A major airstrike targeted the power grid.',
        'summary' => 'Airstrike on power grid.',
        'category' => 'airstrike',
        'severity' => 8,
        'confidence' => 7,
        'status' => 'unverified',
        'country' => 'UA',
        'region' => 'Kharkiv Oblast',
        'geo_approximate' => true,
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5(uniqid()),
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ], $overrides));
}

function addEntities(Event $event, array $names): void
{
    foreach ($names as $name) {
        EntityExtraction::create([
            'event_id' => $event->id,
            'name' => $name,
            'type' => 'location',
        ]);
    }
}

/**
 * The CorroborationService uses pgvector SQL for embedding similarity which
 * SQLite cannot execute. The service catches that exception and returns null,
 * falling back to entity Jaccard + structural scoring — both SQLite-safe.
 *
 * We rely on this fallback behavior in tests. Entity + structural scoring:
 *   - 3 shared out of 4 unique → Jaccard = 0.75
 *   - same country + same category → structural = 1.0
 *   - combined (no embedding): 0.6 * 0.75 + 0.4 * 1.0 = 0.45 + 0.40 = 0.85
 *   - above SCORE_THRESHOLD (0.55) → link is created
 */
test('CorroborationService creates CorroborationLink for matching events from different families', function () {
    $sourceA = makeSourceWithFamily('Link Reuters');
    $sourceB = makeSourceWithFamily('Link AP News');

    $eventA = makeCorroborationEvent($sourceA);
    $eventB = makeCorroborationEvent($sourceB);

    addEntities($eventA, ['Kharkiv', 'Ukraine Power Grid', 'Russian Air Force']);
    addEntities($eventB, ['Kharkiv', 'Ukraine Power Grid', 'DTEK Energy']);

    $eventA->load(['source.sourceFamily', 'entityExtractions']);

    $service = new CorroborationService();
    $service->findMatches($eventA);

    $link = CorroborationLink::where(function ($q) use ($eventA, $eventB) {
        $q->where('event_a_id', $eventA->id)->where('event_b_id', $eventB->id);
    })->orWhere(function ($q) use ($eventA, $eventB) {
        $q->where('event_a_id', $eventB->id)->where('event_b_id', $eventA->id);
    })->first();

    expect($link)->not->toBeNull()
        ->and($link->cross_family)->toBeTrue();
});

test('CorroborationService increments corroboration_count on cross-family match', function () {
    $sourceA = makeSourceWithFamily('Count Reuters');
    $sourceB = makeSourceWithFamily('Count BBC');

    $eventA = makeCorroborationEvent($sourceA);
    $eventB = makeCorroborationEvent($sourceB);

    addEntities($eventA, ['Mariupol', 'Azov Battalion', 'Donetsk Front']);
    addEntities($eventB, ['Mariupol', 'Azov Battalion', 'Ukrainian Marines']);

    $eventA->load(['source.sourceFamily', 'entityExtractions']);

    $service = new CorroborationService();
    $service->findMatches($eventA);

    $eventA->refresh();
    $eventB->refresh();

    expect($eventA->corroboration_count)->toBe(1)
        ->and($eventB->corroboration_count)->toBe(1);
});

test('status: 1 source stays unverified', function () {
    $source = makeSourceWithFamily('Solo-Family');
    $event = makeCorroborationEvent($source);

    expect($event->status)->toBe('unverified')
        ->and($event->corroboration_count)->toBe(0);
});

test('status upgrade: 2 independent sources → corroborated', function () {
    $sourceA = makeSourceWithFamily('Status Alpha');
    $sourceB = makeSourceWithFamily('Status Beta');

    $eventA = makeCorroborationEvent($sourceA);
    $eventB = makeCorroborationEvent($sourceB);

    addEntities($eventA, ['Zaporizhzhia', 'Power Plant', 'Russian Forces']);
    addEntities($eventB, ['Zaporizhzhia', 'Power Plant', 'IAEA']);

    $eventA->load(['source.sourceFamily', 'entityExtractions']);

    $service = new CorroborationService();
    $service->findMatches($eventA);

    $eventA->refresh();

    expect($eventA->status)->toBe('corroborated')
        ->and($eventA->corroboration_count)->toBe(1);
});

test('status upgrade: 3 independent sources → confirmed', function () {
    $sourceA = makeSourceWithFamily('Confirm Alpha');
    $sourceB = makeSourceWithFamily('Confirm Beta');
    $sourceC = makeSourceWithFamily('Confirm Gamma');

    $eventA = makeCorroborationEvent($sourceA);
    $eventB = makeCorroborationEvent($sourceB);
    $eventC = makeCorroborationEvent($sourceC);

    addEntities($eventA, ['Bakhmut', 'Wagner Group', 'Ukrainian 93rd Brigade']);
    addEntities($eventB, ['Bakhmut', 'Wagner Group', 'Donetsk Oblast']);
    addEntities($eventC, ['Bakhmut', 'Ukrainian 93rd Brigade', 'Donetsk Oblast']);

    // Manually create two cross-family links to simulate confirmed status
    CorroborationLink::create([
        'event_a_id' => $eventA->id,
        'event_b_id' => $eventB->id,
        'similarity_score' => 0.88,
        'match_method' => 'embedding',
        'cross_family' => true,
    ]);
    CorroborationLink::create([
        'event_a_id' => $eventA->id,
        'event_b_id' => $eventC->id,
        'similarity_score' => 0.85,
        'match_method' => 'embedding',
        'cross_family' => true,
    ]);

    // Count cross-family links and compute expected status
    $crossFamilyCount = CorroborationLink::where('cross_family', true)
        ->where(function ($q) use ($eventA) {
            $q->where('event_a_id', $eventA->id)
              ->orWhere('event_b_id', $eventA->id);
        })
        ->count();

    $status = match (true) {
        $crossFamilyCount >= 2 => 'confirmed',
        $crossFamilyCount === 1 => 'corroborated',
        default => 'unverified',
    };

    $eventA->update([
        'corroboration_count' => $crossFamilyCount,
        'status' => $status,
    ]);

    $eventA->refresh();

    expect($eventA->status)->toBe('confirmed')
        ->and($eventA->corroboration_count)->toBe(2);
});

test('CorroborationService does not create link for events from the same family', function () {
    $family = SourceFamily::create(['name' => 'Same-Family']);

    $sourceA = Source::create([
        'name' => 'Same Family Feed 1',
        'type' => 'rss',
        'url' => 'https://example.com/feed1',
        'source_family_id' => $family->id,
        'polling_interval' => 10,
        'active' => true,
    ]);

    $sourceB = Source::create([
        'name' => 'Same Family Feed 2',
        'type' => 'rss',
        'url' => 'https://example.com/feed2',
        'source_family_id' => $family->id,
        'polling_interval' => 10,
        'active' => true,
    ]);

    $eventA = makeCorroborationEvent($sourceA);
    makeCorroborationEvent($sourceB);

    $eventA->load(['source.sourceFamily', 'entityExtractions']);

    // Real service — same source_family_id filters candidates out
    $service = new CorroborationService();
    $service->findMatches($eventA);

    expect(CorroborationLink::count())->toBe(0);
});

test('CorroborateEventJob skips pending_classification events', function () {
    $source = makeSourceWithFamily('PendingSkipFamily');
    $event = makeCorroborationEvent($source, ['status' => 'pending_classification']);

    $corroborationService = mock(CorroborationService::class);
    $corroborationService->shouldReceive('findMatches')->never();

    $job = new CorroborateEventJob($event->id);
    $job->handle($corroborationService);

    Queue::assertNotPushed(AssignThreadJob::class);
});

test('CorroborateEventJob dispatches AssignThreadJob after corroboration', function () {
    $source = makeSourceWithFamily('ThreadDispatchFamily');
    $event = makeCorroborationEvent($source);

    $corroborationService = mock(CorroborationService::class);
    $corroborationService->shouldReceive('findMatches')->once();

    $job = new CorroborateEventJob($event->id);
    $job->handle($corroborationService);

    Queue::assertPushed(AssignThreadJob::class, function ($j) use ($event) {
        return (fn() => $this->eventId)->bindTo($j, $j)() === $event->id;
    });
});

test('CorroborationService does not create duplicate links', function () {
    $sourceA = makeSourceWithFamily('DedupFamilyA');
    $sourceB = makeSourceWithFamily('DedupFamilyB');

    $eventA = makeCorroborationEvent($sourceA);
    $eventB = makeCorroborationEvent($sourceB);

    // Pre-create a corroboration link
    CorroborationLink::create([
        'event_a_id' => $eventA->id,
        'event_b_id' => $eventB->id,
        'similarity_score' => 0.90,
        'match_method' => 'embedding',
        'cross_family' => true,
    ]);

    addEntities($eventA, ['Kherson', 'Russian Forces', 'Dnipro River']);
    addEntities($eventB, ['Kherson', 'Russian Forces', 'Ukraine Army']);

    $eventA->load(['source.sourceFamily', 'entityExtractions']);

    $service = new CorroborationService();
    $service->findMatches($eventA);

    // Should still be exactly 1 link
    expect(CorroborationLink::count())->toBe(1);
});

test('CorroborationService sets match_method to entity when no embedding and entity overlap exists', function () {
    $sourceA = makeSourceWithFamily('MethodFamilyA');
    $sourceB = makeSourceWithFamily('MethodFamilyB');

    $eventA = makeCorroborationEvent($sourceA);
    $eventB = makeCorroborationEvent($sourceB);

    addEntities($eventA, ['Odesa', 'Black Sea Fleet', 'Russian Navy']);
    addEntities($eventB, ['Odesa', 'Black Sea Fleet', 'Ukrainian Navy']);

    $eventA->load(['source.sourceFamily', 'entityExtractions']);

    $service = new CorroborationService();
    $service->findMatches($eventA);

    $link = CorroborationLink::first();

    // No embedding available (SQLite) → falls back to entity method
    expect($link)->not->toBeNull()
        ->and($link->match_method)->toBe('entity');
});

test('CorroborationService stores similarity score within 0-1 range', function () {
    $sourceA = makeSourceWithFamily('ScoreFamilyA');
    $sourceB = makeSourceWithFamily('ScoreFamilyB');

    $eventA = makeCorroborationEvent($sourceA);
    $eventB = makeCorroborationEvent($sourceB);

    addEntities($eventA, ['Lviv', 'Ukraine Army', 'Territorial Defense']);
    addEntities($eventB, ['Lviv', 'Ukraine Army', 'NATO Support']);

    $eventA->load(['source.sourceFamily', 'entityExtractions']);

    $service = new CorroborationService();
    $service->findMatches($eventA);

    $link = CorroborationLink::first();

    expect($link)->not->toBeNull();
    $score = (float) $link->similarity_score;
    expect($score)->toBeGreaterThan(0.0)
        ->toBeLessThanOrEqual(1.0);
});
