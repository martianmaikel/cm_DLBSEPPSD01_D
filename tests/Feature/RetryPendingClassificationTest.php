<?php

use App\Jobs\ProcessRawEventJob;
use App\Models\Event;
use App\Models\Source;
use App\Models\SourceFamily;
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

function makeRetrySource(): Source
{
    $family = SourceFamily::create(['name' => 'Retry Family ' . uniqid()]);
    return Source::create([
        'name' => 'Retry Source',
        'type' => 'rss',
        'url' => 'https://example.com/feed',
        'source_family_id' => $family->id,
        'polling_interval' => 10,
        'active' => true,
    ]);
}

function makePendingEvent(Source $source, array $overrides = []): Event
{
    return Event::create(array_merge([
        'title' => 'Pending event',
        'raw_content' => 'Content that failed classification.',
        'summary' => '',
        'category' => 'other',
        'severity' => 1,
        'confidence' => 1,
        'status' => 'pending_classification',
        'country' => null,
        'geo_approximate' => false,
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5(uniqid()),
        'occurred_at' => now()->subHour(),
        'classification_attempts' => 1,
        'updated_at' => now()->subMinutes(10), // old enough to retry
    ], $overrides));
}

test('events:retry-classification dispatches ProcessRawEventJob for eligible events', function () {
    $source = makeRetrySource();
    makePendingEvent($source);
    makePendingEvent($source, ['hash' => md5(uniqid())]);

    $this->artisan('events:retry-classification')->assertExitCode(0);

    Queue::assertPushed(ProcessRawEventJob::class, 2);
});

test('events:retry-classification dispatches job with correct payload', function () {
    $source = makeRetrySource();
    $event = makePendingEvent($source);

    $this->artisan('events:retry-classification')->assertExitCode(0);

    Queue::assertPushed(ProcessRawEventJob::class, function ($job) use ($event) {
        $payload = (fn() => $this->payload)->bindTo($job, $job)();
        return $payload['title'] === $event->title
            && $payload['raw_content'] === $event->raw_content
            && $payload['source_id'] === $event->source_id
            && $payload['hash'] === $event->hash;
    });
});

test('events:retry-classification skips events with 5 or more attempts', function () {
    $source = makeRetrySource();
    makePendingEvent($source, ['classification_attempts' => 5]);
    makePendingEvent($source, ['hash' => md5(uniqid()), 'classification_attempts' => 6]);

    $this->artisan('events:retry-classification')->assertExitCode(0);

    Queue::assertNothingPushed();
});

test('events:retry-classification skips events updated within the last 5 minutes', function () {
    $source = makeRetrySource();

    // updated_at is recent (2 minutes ago) — too soon to retry
    Event::create([
        'title' => 'Too recent',
        'raw_content' => 'Content.',
        'summary' => '',
        'category' => 'other',
        'severity' => 1,
        'confidence' => 1,
        'status' => 'pending_classification',
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5(uniqid()),
        'occurred_at' => now()->subHour(),
        'classification_attempts' => 1,
        'updated_at' => now()->subMinutes(2),
        'created_at' => now()->subHour(),
    ]);

    $this->artisan('events:retry-classification')->assertExitCode(0);

    Queue::assertNothingPushed();
});

test('events:retry-classification outputs info when no events pending', function () {
    $this->artisan('events:retry-classification')
        ->assertExitCode(0)
        ->expectsOutput('No events pending retry.');
});

test('events:retry-classification only retries pending_classification status', function () {
    $source = makeRetrySource();

    // Non-pending events should not be retried
    Event::create([
        'title' => 'Unverified event',
        'raw_content' => 'Content.',
        'summary' => 'Summary.',
        'category' => 'airstrike',
        'severity' => 5,
        'confidence' => 5,
        'status' => 'unverified',
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5(uniqid()),
        'occurred_at' => now()->subHour(),
        'classification_attempts' => 1,
        'updated_at' => now()->subHour(),
    ]);

    Event::create([
        'title' => 'Corroborated event',
        'raw_content' => 'Content.',
        'summary' => 'Summary.',
        'category' => 'artillery',
        'severity' => 7,
        'confidence' => 8,
        'status' => 'corroborated',
        'source_id' => $source->id,
        'corroboration_count' => 1,
        'hash' => md5(uniqid()),
        'occurred_at' => now()->subHour(),
        'classification_attempts' => 1,
        'updated_at' => now()->subHour(),
    ]);

    $this->artisan('events:retry-classification')->assertExitCode(0);

    Queue::assertNothingPushed();
});

test('events:retry-classification retries events with 1 to 4 attempts', function () {
    $source = makeRetrySource();

    foreach ([1, 2, 3, 4] as $attempts) {
        makePendingEvent($source, [
            'hash' => md5(uniqid() . $attempts),
            'classification_attempts' => $attempts,
        ]);
    }

    // 5 attempts — should be skipped
    makePendingEvent($source, [
        'hash' => md5(uniqid() . 'max'),
        'classification_attempts' => 5,
    ]);

    $this->artisan('events:retry-classification')->assertExitCode(0);

    Queue::assertPushed(ProcessRawEventJob::class, 4);
});
