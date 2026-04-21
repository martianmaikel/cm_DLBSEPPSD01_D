<?php

/**
 * Tests for the dual-bucket hashing strategy used in RssIngestionService.
 *
 * The production code hashes: md5(title + source_id + bucket)
 * where bucket = floor(unix_timestamp / 300).
 *
 * This means two items with the same title published within the same 5-minute
 * window produce the same hash. Items straddling a bucket boundary produce a
 * different hash but the previous-bucket hash is also checked so near-boundary
 * duplicates are still caught.
 */

function makeHash(string $title, int|string $sourceId, int $timestamp): string
{
    $bucket = (int) floor($timestamp / 300);
    return md5($title . $sourceId . $bucket);
}

function makePrevHash(string $title, int|string $sourceId, int $timestamp): string
{
    $bucket = (int) floor($timestamp / 300);
    return md5($title . $sourceId . ($bucket - 1));
}

test('same title and source in the same 5-minute bucket produce identical hash', function () {
    $title = 'Airstrike reported near Kyiv';
    $sourceId = 42;

    // Two timestamps 120 seconds apart, both in bucket floor(T/300)
    $base = 1_700_000_000; // arbitrary Unix timestamp
    $bucket = (int) floor($base / 300) * 300; // start of a bucket

    $hash1 = makeHash($title, $sourceId, $bucket + 10);
    $hash2 = makeHash($title, $sourceId, $bucket + 180);

    expect($hash1)->toBe($hash2);
});

test('same title and source in different 5-minute buckets produce different hashes', function () {
    $title = 'Artillery fire reported';
    $sourceId = 7;

    $base = 1_700_000_000;
    $bucket = (int) floor($base / 300) * 300;

    $hash1 = makeHash($title, $sourceId, $bucket + 10);   // bucket N
    $hash2 = makeHash($title, $sourceId, $bucket + 310);  // bucket N+1

    expect($hash1)->not->toBe($hash2);
});

test('different titles in the same bucket produce different hashes', function () {
    $sourceId = 1;
    $timestamp = 1_700_000_100;

    $hash1 = makeHash('Airstrike near Kyiv', $sourceId, $timestamp);
    $hash2 = makeHash('Artillery fire in Donetsk', $sourceId, $timestamp);

    expect($hash1)->not->toBe($hash2);
});

test('same title from different sources produce different hashes', function () {
    $title = 'Troops spotted at border';
    $timestamp = 1_700_000_100;

    $hash1 = makeHash($title, 1, $timestamp);
    $hash2 = makeHash($title, 2, $timestamp);

    expect($hash1)->not->toBe($hash2);
});

test('hash is a valid 32-character md5 hex string', function () {
    $hash = makeHash('Test event', 1, 1_700_000_100);

    expect($hash)->toMatch('/^[a-f0-9]{32}$/');
});

test('previous-bucket hash is different from current-bucket hash', function () {
    $title = 'Naval incident in Black Sea';
    $sourceId = 5;
    $timestamp = 1_700_000_100;

    $current = makeHash($title, $sourceId, $timestamp);
    $prev = makePrevHash($title, $sourceId, $timestamp);

    expect($current)->not->toBe($prev);
});

test('item at bucket boundary is caught by previous-bucket hash', function () {
    // Simulate an item published just at the start of a new bucket (second 0).
    // The previous-bucket hash of this item matches the canonical hash of the same
    // item published 1 second before the boundary (still in the old bucket).
    $title = 'Drone strike reported';
    $sourceId = 3;

    $base = 1_700_000_000;
    $bucketStart = (int) floor($base / 300) * 300 + 300; // start of next bucket

    $hashAtBoundary = makeHash($title, $sourceId, $bucketStart);      // new bucket
    $hashBeforeBoundary = makeHash($title, $sourceId, $bucketStart - 1); // old bucket

    // They differ because they are in different buckets
    expect($hashAtBoundary)->not->toBe($hashBeforeBoundary);

    // But the previous-bucket hash of the boundary item equals the old-bucket canonical hash
    $prevHashOfBoundaryItem = makePrevHash($title, $sourceId, $bucketStart);
    expect($prevHashOfBoundaryItem)->toBe($hashBeforeBoundary);
});

test('dual-bucket check: at least one of the two hashes matches for near-boundary items', function () {
    $title = 'Explosion near checkpoint';
    $sourceId = 9;

    $base = 1_700_000_000;
    $bucketStart = (int) floor($base / 300) * 300 + 300;

    // Item published 1 second before the boundary — its canonical hash
    $canonicalOld = makeHash($title, $sourceId, $bucketStart - 1);

    // Item published just after the boundary — check both its hashes
    $currentNew = makeHash($title, $sourceId, $bucketStart);
    $prevNew = makePrevHash($title, $sourceId, $bucketStart);

    // The prev-bucket hash of the new item matches the canonical hash of the old item
    expect($prevNew)->toBe($canonicalOld);
    // The canonical hashes differ (different buckets)
    expect($currentNew)->not->toBe($canonicalOld);
});
