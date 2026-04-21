<?php

namespace App\Services\Newsletter;

use App\Models\ConflictThread;
use App\Models\DailyBriefing;
use App\Models\Event;
use App\Models\NewsletterAffiliate;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Carbon;

class BuildDailyNewsletterContent
{
    public function __construct(private AffiliateRotator $affiliateRotator)
    {
    }


    /**
     * Number of top events to include in the newsletter.
     */
    public const int TOP_EVENTS_LIMIT = 5;

    /**
     * Number of events per subscribed thread digest.
     */
    public const int PER_THREAD_EVENTS_LIMIT = 2;

    /**
     * Build the rendering payload for a subscriber's daily global briefing email.
     *
     * @return array{
     *     locale: string,
     *     subject: string,
     *     preheader: string,
     *     include_global: bool,
     *     briefing: ?array<string, mixed>,
     *     events: array<int, array<string, mixed>>,
     *     thread_digests: array<int, array<string, mixed>>,
     *     unsubscribe_url: string,
     *     preferences_url: string
     * }
     */
    public function build(NewsletterSubscriber $subscriber, ?Carbon $date = null): array
    {
        $date ??= Carbon::today($subscriber->timezone);
        $locale = $subscriber->locale;
        $includeGlobal = (bool) $subscriber->wants_global_digest;

        // --- Global briefing section ---
        $briefingPayload = null;
        $globalEvents = [];
        if ($includeGlobal) {
            $briefing = DailyBriefing::forDate($date)->first()
                ?? DailyBriefing::latest()->first(); // fallback to most recent

            if ($briefing) {
                $summary = $locale === 'de' ? $briefing->summary_de : $briefing->summary_en;
                $briefingPayload = [
                    'title' => $briefing->title ?? '',
                    'summary' => $summary ?? '',
                    'key_developments' => $briefing->key_developments ?? [],
                    'statistics' => $briefing->statistics ?? [],
                    'date' => $briefing->briefing_date->format('Y-m-d'),
                ];
            }
            $globalEvents = $this->fetchTopEvents();
        }

        // --- Per-thread digest sections ---
        $threadDigests = $this->buildThreadDigests($subscriber);

        // --- Affiliate slot (rotated per send) ---
        $affiliate = $this->pickAffiliate($locale, $subscriber);

        $preheader = $briefingPayload['summary']
            ?? ($threadDigests[0]['name'] ?? '')
            ?? '';

        return [
            'locale' => $locale,
            'subject' => $this->buildSubject($date, $locale),
            'preheader' => $preheader,
            'include_global' => $includeGlobal,
            'briefing' => $briefingPayload,
            'events' => $globalEvents,
            'thread_digests' => $threadDigests,
            'affiliate' => $affiliate,
            'unsubscribe_url' => url('/newsletter/unsubscribe/'.$subscriber->unsubscribe_token),
            'preferences_url' => url('/newsletter/preferences/'.$subscriber->preferences_token),
        ];
    }

    /**
     * Select an affiliate, track impression, return localized payload for template.
     * Returns null when no live affiliate is available.
     */
    private function pickAffiliate(string $locale, NewsletterSubscriber $subscriber): ?array
    {
        $aff = $this->affiliateRotator->pickOne();
        if (! $aff) {
            return null;
        }
        $this->affiliateRotator->trackImpression($aff);

        return [
            'id' => $aff->id,
            'slug' => $aff->slug,
            'headline' => $aff->getLocalizedHeadline($locale),
            'body' => $aff->getLocalizedBody($locale),
            'cta' => $aff->getLocalizedCta($locale),
            'image_url' => $aff->image_url,
            'url' => url('/r/a/'.$aff->slug.'?s='.substr($subscriber->id, 0, 8)),
        ];
    }

    /**
     * Fetch top global events from the last 24h.
     */
    private function fetchTopEvents(): array
    {
        return $this->formatEvents(
            Event::query()
                ->whereIn('status', ['corroborated', 'confirmed'])
                ->where('occurred_at', '>=', now()->subHours(24))
                ->whereNotNull('severity')
                ->whereNotNull('confidence')
                ->orderByRaw('severity DESC NULLS LAST')
                ->orderByDesc('confidence')
                ->orderByDesc('occurred_at')
                ->limit(self::TOP_EVENTS_LIMIT)
                ->get($this->eventColumns())
        );
    }

    /**
     * Build per-thread digest sections for subscribed threads with wants_digest=true.
     */
    private function buildThreadDigests(NewsletterSubscriber $subscriber): array
    {
        $threads = $subscriber->threads()
            ->wherePivot('wants_digest', true)
            ->get();

        if ($threads->isEmpty()) {
            return [];
        }

        return $threads->map(function (ConflictThread $thread) {
            $events = Event::query()
                ->where('conflict_thread_id', $thread->id)
                ->whereIn('status', ['corroborated', 'confirmed'])
                ->where('occurred_at', '>=', now()->subHours(24))
                ->whereNotNull('severity')
                ->orderByRaw('severity DESC NULLS LAST')
                ->orderByDesc('confidence')
                ->orderByDesc('occurred_at')
                ->limit(self::PER_THREAD_EVENTS_LIMIT)
                ->get($this->eventColumns());

            return [
                'id' => $thread->id,
                'name' => $thread->name,
                'slug' => $thread->slug,
                'summary' => $thread->summary,
                'event_count_24h' => $thread->event_count_24h,
                'max_severity' => $thread->max_severity,
                'url' => url('/thread/'.$thread->id),
                'events' => $this->formatEvents($events),
            ];
        })
            // Drop threads that had no recent events so we don't render empty sections
            ->filter(fn (array $digest) => ! empty($digest['events']))
            ->values()
            ->toArray();
    }

    private function eventColumns(): array
    {
        return [
            'id', 'title', 'summary', 'severity', 'confidence',
            'status', 'country', 'category', 'source_url', 'occurred_at',
            'conflict_thread_id',
        ];
    }

    private function formatEvents(\Illuminate\Support\Collection $events): array
    {
        return $events->map(fn (Event $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'summary' => $e->summary,
            'severity' => $e->severity,
            'confidence' => $e->confidence,
            'status' => $e->status,
            'country' => $e->country,
            'category' => $e->category,
            'source_url' => $e->source_url,
            'occurred_at' => $e->occurred_at?->toIso8601String(),
            'event_url' => url('/event/'.$e->id),
        ])->toArray();
    }

    private function buildSubject(Carbon $date, string $locale): string
    {
        $formatted = $locale === 'de'
            ? $date->locale('de')->isoFormat('D. MMMM YYYY')
            : $date->format('M j, Y');

        return __('newsletter.daily.subject', ['date' => $formatted], $locale);
    }
}
