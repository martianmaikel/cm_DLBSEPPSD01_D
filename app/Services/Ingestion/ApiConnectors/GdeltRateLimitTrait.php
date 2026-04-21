<?php

namespace App\Services\Ingestion\ApiConnectors;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Shared rate-limit backoff for GDELT API connectors.
 *
 * Stores a "backoff until" timestamp in Redis. When a 429 is received,
 * the backoff doubles (starting at 30 min, capped at 2 hours).
 * A successful request clears the backoff.
 */
trait GdeltRateLimitTrait
{
    private static string $backoffKeyPrefix = 'gdelt:backoff:';
    private static int $backoffInitial = 1800;   // 30 min
    private static int $backoffMax = 7200;        // 2 hours

    private function isBackedOff(int $sourceId): bool
    {
        $backoffUntil = Redis::get(self::$backoffKeyPrefix . $sourceId);

        if ($backoffUntil && time() < (int) $backoffUntil) {
            $remaining = (int) $backoffUntil - time();
            Log::debug('GDELT: skipping poll, rate-limit backoff active', [
                'source_id' => $sourceId,
                'retry_in_seconds' => $remaining,
            ]);

            return true;
        }

        return false;
    }

    private function applyBackoff(int $sourceId): void
    {
        $key = self::$backoffKeyPrefix . $sourceId;
        $existing = Redis::get($key);

        // Double previous backoff if already backing off, otherwise start at initial
        $duration = $existing
            ? min(self::$backoffMax, self::$backoffInitial * 2)
            : self::$backoffInitial;

        Redis::setex($key, $duration, (string) (time() + $duration));
    }

    private function clearBackoff(int $sourceId): void
    {
        Redis::del(self::$backoffKeyPrefix . $sourceId);
    }
}
