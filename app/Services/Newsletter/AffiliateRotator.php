<?php

namespace App\Services\Newsletter;

use App\Models\NewsletterAffiliate;

class AffiliateRotator
{
    /**
     * Pick one live affiliate using weighted random selection with
     * an impression-balance boost for under-served affiliates.
     *
     * Returns null when no affiliate is active/live.
     */
    public function pickOne(): ?NewsletterAffiliate
    {
        $affiliates = NewsletterAffiliate::live()->get();

        if ($affiliates->isEmpty()) {
            return null;
        }

        if ($affiliates->count() === 1) {
            return $affiliates->first();
        }

        $avgImpressions = max(1, (int) $affiliates->avg('impression_count'));

        $weighted = $affiliates->map(function (NewsletterAffiliate $a) use ($avgImpressions) {
            // ratio > 1 → over-served (penalty), ratio < 1 → under-served (boost up to +20%)
            $ratio = min(2.0, $a->impression_count / $avgImpressions);
            $boost = 1 + (1 - $ratio) * 0.2; // range: 0.8 .. 1.2
            return [
                'affiliate' => $a,
                'effective_weight' => max(1, (int) round($a->weight * $boost)),
            ];
        });

        $total = $weighted->sum('effective_weight');
        $rand = random_int(1, $total);

        $cumulative = 0;
        foreach ($weighted as $item) {
            $cumulative += $item['effective_weight'];
            if ($rand <= $cumulative) {
                return $item['affiliate'];
            }
        }

        return $weighted->last()['affiliate'] ?? null;
    }

    /**
     * Record an impression atomically.
     */
    public function trackImpression(NewsletterAffiliate $affiliate): void
    {
        $affiliate->increment('impression_count');
    }
}
