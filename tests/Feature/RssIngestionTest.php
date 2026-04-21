<?php

use App\Jobs\ProcessRawEventJob;
use App\Models\Source;
use App\Models\SourceFamily;
use App\Services\Ingestion\RssIngestionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\Support\CreatesSqliteSchema;

uses(Tests\TestCase::class, CreatesSqliteSchema::class);

beforeEach(function () {
    $this->createTestSchema();
});

afterEach(function () {
    $this->dropTestSchema();
});

function makeFamilyAndSource(): Source
{
    $family = SourceFamily::create(['name' => 'Reuters']);
    return Source::create([
        'name' => 'Reuters RSS',
        'type' => 'rss',
        'url' => 'https://feeds.reuters.com/reuters/topNews',
        'source_family_id' => $family->id,
        'polling_interval' => 10,
        'active' => true,
    ]);
}

function rssXml(array $items = []): string
{
    $itemsXml = '';
    foreach ($items as $item) {
        $title = htmlspecialchars($item['title'] ?? 'No title');
        $desc = htmlspecialchars($item['description'] ?? '');
        $date = $item['pubDate'] ?? 'Mon, 30 Mar 2026 12:00:00 +0000';
        $itemsXml .= "<item><title>{$title}</title><description>{$desc}</description><pubDate>{$date}</pubDate></item>\n";
    }

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Reuters Top News</title>
    <link>https://reuters.com</link>
    {$itemsXml}
  </channel>
</rss>
XML;
}

test('poll dispatches ProcessRawEventJob for each RSS item', function () {
    Queue::fake();
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('setex')->andReturn(true);

    $source = makeFamilyAndSource();

    Http::fake([
        $source->url => Http::response(rssXml([
            ['title' => 'Airstrike near Kyiv', 'description' => 'An airstrike was reported.'],
            ['title' => 'Troops advance in Donbas', 'description' => 'Troops have been spotted moving.'],
        ]), 200),
    ]);

    $service = new RssIngestionService();
    $service->poll($source);

    Queue::assertPushed(ProcessRawEventJob::class, 2);
});

test('poll dispatches job with correct payload fields', function () {
    Queue::fake();
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('setex')->andReturn(true);

    $source = makeFamilyAndSource();

    Http::fake([
        $source->url => Http::response(rssXml([
            ['title' => 'Artillery fire near Kherson', 'description' => 'Heavy artillery reported.'],
        ]), 200),
    ]);

    $service = new RssIngestionService();
    $service->poll($source);

    Queue::assertPushed(ProcessRawEventJob::class, function ($job) use ($source) {
        $payload = (fn() => $this->payload)->bindTo($job, $job)();
        return $payload['title'] === 'Artillery fire near Kherson'
            && $payload['source_id'] === $source->id
            && isset($payload['hash'])
            && isset($payload['raw_content'])
            && isset($payload['occurred_at']);
    });
});

test('poll skips item when current-bucket hash exists in Redis', function () {
    Queue::fake();

    $source = makeFamilyAndSource();

    Http::fake([
        $source->url => Http::response(rssXml([
            ['title' => 'Duplicate event', 'description' => 'Already seen.'],
        ]), 200),
    ]);

    // Redis returns a hit for both hashes (current and previous bucket)
    Redis::shouldReceive('get')->andReturn('1');

    $service = new RssIngestionService();
    $service->poll($source);

    Queue::assertNothingPushed();
});

test('poll skips item when previous-bucket hash exists in Redis', function () {
    Queue::fake();

    $source = makeFamilyAndSource();

    Http::fake([
        $source->url => Http::response(rssXml([
            ['title' => 'Near-boundary duplicate', 'description' => 'Boundary case.'],
        ]), 200),
    ]);

    // First get (current bucket) returns null, second get (prev bucket) returns hit
    Redis::shouldReceive('get')
        ->andReturn(null, '1');

    $service = new RssIngestionService();
    $service->poll($source);

    Queue::assertNothingPushed();
});

test('poll stores canonical hash in Redis with 48h TTL', function () {
    Queue::fake();

    $source = makeFamilyAndSource();

    Http::fake([
        $source->url => Http::response(rssXml([
            ['title' => 'New event to cache'],
        ]), 200),
    ]);

    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('setex')
        ->once()
        ->with(
            \Mockery::pattern('/^event_hash:[a-f0-9]{32}$/'),
            172800,
            '1'
        )
        ->andReturn(true);

    $service = new RssIngestionService();
    $service->poll($source);
});

test('poll handles HTTP failure gracefully without throwing', function () {
    Queue::fake();

    $source = makeFamilyAndSource();

    Http::fake([
        $source->url => Http::response('', 500),
    ]);

    $service = new RssIngestionService();

    expect(fn() => $service->poll($source))->not->toThrow(\Throwable::class);

    Queue::assertNothingPushed();
});

test('poll handles invalid XML gracefully without throwing', function () {
    Queue::fake();

    $source = makeFamilyAndSource();

    Http::fake([
        $source->url => Http::response('this is not XML', 200),
    ]);

    $service = new RssIngestionService();

    expect(fn() => $service->poll($source))->not->toThrow(\Throwable::class);

    Queue::assertNothingPushed();
});

test('poll skips items with empty title and empty description', function () {
    Queue::fake();
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('setex')->andReturn(true);

    $source = makeFamilyAndSource();

    Http::fake([
        $source->url => Http::response(rssXml([
            ['title' => '', 'description' => ''],
        ]), 200),
    ]);

    $service = new RssIngestionService();
    $service->poll($source);

    Queue::assertNothingPushed();
});
