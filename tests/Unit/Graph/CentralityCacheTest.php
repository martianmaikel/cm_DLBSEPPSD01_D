<?php

use App\Services\Graph\CentralityCache;
use App\Services\Graph\Graph;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(fn () => Cache::flush());

describe('CentralityCache', function () {
    it('computes on a miss and serves the cached value on a hit', function () {
        $cache = new CentralityCache();
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return ['a' => 1.0];
        };

        $first = $cache->remember('degree', 'sig1', $compute);
        $second = $cache->remember('degree', 'sig1', $compute);

        expect($first)->toBe(['a' => 1.0])
            ->and($second)->toBe(['a' => 1.0])
            ->and($calls)->toBe(1); // computed once, second call served from cache
    });

    it('recomputes when the signature changes (version invalidation)', function () {
        $cache = new CentralityCache();
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return [];
        };

        $cache->remember('degree', 'sig1', $compute);
        $cache->remember('degree', 'sig2', $compute); // graph changed → new version

        expect($calls)->toBe(2);
    });

    it('keeps a separate entry per metric', function () {
        $cache = new CentralityCache();
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return [];
        };

        $cache->remember('degree', 'sig1', $compute);
        $cache->remember('betweenness', 'sig1', $compute);

        expect($calls)->toBe(2);
    });

    it('derives a signature that is stable per structure and changes with it', function () {
        $cache = new CentralityCache();

        $g1 = new Graph();
        $g1->addEdge('a', 'b');

        $g2 = new Graph();
        $g2->addEdge('a', 'b');

        $g3 = new Graph();
        $g3->addEdge('a', 'b');
        $g3->addEdge('b', 'c');

        expect($cache->signatureFor($g1))->toBe($cache->signatureFor($g2))     // same structure
            ->and($cache->signatureFor($g1))->not->toBe($cache->signatureFor($g3)); // different structure
    });
});
