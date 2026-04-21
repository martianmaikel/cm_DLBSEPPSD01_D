<?php

namespace App\Services\Social;

use App\Models\Event;

class RelevanceScorer
{
    private const STATUS_WEIGHTS = [
        'confirmed' => 1.0,
        'corroborated' => 0.9,
        'unverified' => 0.6,
        'pending_classification' => 0.4,
        'disputed' => 0.3,
        'retracted' => 0.0,
    ];

    /**
     * Determine if an event is relevant enough for social media posting.
     * Passes if weighted severity meets threshold OR any severity factor >= factor threshold.
     * Status gate always applies.
     */
    public function isRelevant(Event $event): bool
    {
        $allowedStatuses = config('social.relevance.allowed_statuses', ['corroborated', 'confirmed']);

        if (! in_array($event->status, $allowedStatuses, true)) {
            return false;
        }

        $minWeightedSeverity = config('social.relevance.min_weighted_severity', 4.5);

        if ($this->weightedSeverity($event) >= $minWeightedSeverity) {
            return true;
        }

        return $this->hasHighSeverityFactor($event);
    }

    public function hasHighSeverityFactor(Event $event): bool
    {
        $threshold = (int) config('social.relevance.min_factor_severity', 7);
        $factors = $event->severity_factors ?? [];

        foreach ($factors as $value) {
            if (is_numeric($value) && (int) $value >= $threshold) {
                return true;
            }
        }

        return false;
    }

    public function weightedSeverity(Event $event): float
    {
        $statusWeight = self::STATUS_WEIGHTS[$event->status] ?? 0.5;
        $confidenceMultiplier = ($event->confidence ?? 5) / 10;

        return $event->severity * $confidenceMultiplier * $statusWeight;
    }
}
