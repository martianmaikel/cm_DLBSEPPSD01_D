<?php

use App\Jobs\CorroborateEventJob;
use App\Jobs\GeocodeEventJob;
use App\Models\Event;
use App\Models\Source;
use App\Models\SourceFamily;
use App\Services\Geocoding\NominatimService;
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

function makeEventForGeocode(array $overrides = []): Event
{
    $family = SourceFamily::create(['name' => 'Geo Family ' . uniqid()]);
    $source = Source::create([
        'name' => 'Geo Source',
        'type' => 'rss',
        'url' => 'https://example.com/feed',
        'source_family_id' => $family->id,
        'polling_interval' => 10,
        'active' => true,
    ]);

    return Event::create(array_merge([
        'title' => 'Airstrike near Lviv',
        'raw_content' => 'An airstrike hit the outskirts of Lviv.',
        'summary' => 'Airstrike reported near Lviv.',
        'category' => 'airstrike',
        'severity' => 6,
        'confidence' => 5,
        'status' => 'unverified',
        'country' => 'UA',
        'region' => 'Lviv Oblast',
        'geo_approximate' => true,
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5(uniqid()),
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ], $overrides));
}

/**
 * Note on SQLite compatibility:
 *
 * GeocodeEventJob::storeCoordinates() uses DB::statement() with PostGIS functions
 * (ST_SetSRID, ST_MakePoint) that are unavailable in SQLite. This means:
 *
 * - When LLM or Nominatim coordinates ARE available, storeCoordinates() throws on
 *   SQLite before dispatchCorroboration() is reached. These tests wrap the job call
 *   in try/catch and do not assert CorroborateEventJob dispatch for those paths.
 *
 * - When coordinates are NOT available (no location string, Nominatim returns null,
 *   Nominatim throws), the job uses $event->update(['geo_approximate' => true])
 *   which is Eloquent and works on SQLite, then dispatches corroboration normally.
 *   These paths ARE fully testable on SQLite.
 */

test('GeocodeEventJob does not call Nominatim when LLM coordinates provided', function () {
    $event = makeEventForGeocode();

    $geocoder = mock(NominatimService::class);
    $geocoder->shouldReceive('geocode')->never();

    // storeCoordinates() will throw on SQLite — expected
    try {
        (new GeocodeEventJob($event->id, 49.8397, 24.0297))->handle($geocoder);
    } catch (\Throwable) {
        // PostGIS SQL not supported on SQLite
    }
});

test('GeocodeEventJob calls Nominatim when no LLM coordinates provided', function () {
    $event = makeEventForGeocode();

    $geocoder = mock(NominatimService::class);
    $geocoder->shouldReceive('geocode')
        ->once()
        ->with('Lviv Oblast, UA')
        ->andReturn([49.8397, 24.0297]);

    // storeCoordinates() will throw after geocode() returns — expected
    try {
        (new GeocodeEventJob($event->id))->handle($geocoder);
    } catch (\Throwable) {
        // PostGIS SQL not supported on SQLite
    }
});

test('GeocodeEventJob builds correct location string from region and country', function () {
    $event = makeEventForGeocode(['region' => 'Kharkiv Oblast', 'country' => 'UA']);

    $geocoder = mock(NominatimService::class);
    $geocoder->shouldReceive('geocode')
        ->once()
        ->with('Kharkiv Oblast, UA')
        ->andReturn(null);

    (new GeocodeEventJob($event->id))->handle($geocoder);

    $event->refresh();
    expect($event->geo_approximate)->toBeTrue();

    Queue::assertPushed(CorroborateEventJob::class);
});

test('GeocodeEventJob uses only country when region is null', function () {
    $event = makeEventForGeocode(['region' => null, 'country' => 'UA']);

    $geocoder = mock(NominatimService::class);
    $geocoder->shouldReceive('geocode')
        ->once()
        ->with('UA')
        ->andReturn(null);

    (new GeocodeEventJob($event->id))->handle($geocoder);

    Queue::assertPushed(CorroborateEventJob::class);
});

test('GeocodeEventJob sets geo_approximate true and dispatches corroboration when Nominatim returns null', function () {
    $event = makeEventForGeocode();

    $geocoder = mock(NominatimService::class);
    $geocoder->shouldReceive('geocode')->once()->andReturn(null);

    (new GeocodeEventJob($event->id))->handle($geocoder);

    $event->refresh();
    expect($event->geo_approximate)->toBeTrue();

    Queue::assertPushed(CorroborateEventJob::class);
});

test('GeocodeEventJob sets geo_approximate true when no location string available', function () {
    $event = makeEventForGeocode(['region' => null, 'country' => null]);

    $geocoder = mock(NominatimService::class);
    $geocoder->shouldReceive('geocode')->never();

    (new GeocodeEventJob($event->id))->handle($geocoder);

    $event->refresh();
    expect($event->geo_approximate)->toBeTrue();

    Queue::assertPushed(CorroborateEventJob::class);
});

test('GeocodeEventJob dispatches CorroborateEventJob when Nominatim returns null', function () {
    $event = makeEventForGeocode();

    $geocoder = mock(NominatimService::class);
    $geocoder->shouldReceive('geocode')->once()->andReturn(null);

    (new GeocodeEventJob($event->id))->handle($geocoder);

    Queue::assertPushed(CorroborateEventJob::class, function ($j) use ($event) {
        return (fn() => $this->eventId)->bindTo($j, $j)() === $event->id;
    });
});

test('GeocodeEventJob handles Nominatim exception gracefully, sets geo_approximate true, and dispatches corroboration', function () {
    $event = makeEventForGeocode();

    $geocoder = mock(NominatimService::class);
    $geocoder->shouldReceive('geocode')
        ->once()
        ->andThrow(new \RuntimeException('Connection refused'));

    (new GeocodeEventJob($event->id))->handle($geocoder);

    $event->refresh();
    expect($event->geo_approximate)->toBeTrue();

    Queue::assertPushed(CorroborateEventJob::class);
});

test('GeocodeEventJob handles missing event gracefully and dispatches corroboration', function () {
    $geocoder = mock(NominatimService::class);
    $geocoder->shouldReceive('geocode')->never();

    expect(fn() => (new GeocodeEventJob('nonexistent-uuid-0000'))->handle($geocoder))
        ->not->toThrow(\Throwable::class);

    Queue::assertPushed(CorroborateEventJob::class);
});
