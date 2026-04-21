<?php

namespace App\Services\Social;

use App\Models\DailyBriefing;
use App\Models\Event;
use App\Models\SocialChannel;
use Illuminate\Support\Arr;

class ContentBuilder
{
    private const THREADS_MAX_LENGTH = 500;

    private const BLUESKY_MAX_LENGTH = 300;

    private const X_MAX_LENGTH = 280;

    private const CATEGORY_EMOJI = [
        'war' => "\u{1F534}",           // red circle
        'terrorism' => "\u{26A0}\u{FE0F}",  // warning
        'cyber' => "\u{1F4BB}",         // laptop
        'protest' => "\u{270A}",        // raised fist
        'disaster' => "\u{1F30A}",      // wave
        'diplomacy' => "\u{1F54A}\u{FE0F}", // dove
        'economic' => "\u{1F4C9}",      // chart decreasing
    ];

    public function buildEventPost(Event $event, SocialChannel $channel): string
    {
        $locale = $channel->locale;
        $platform = $channel->platform;

        $title = $locale === 'de' ? ($event->title_de ?: $event->title) : $event->title;
        $summary = $locale === 'de' ? ($event->summary_de ?: $event->summary) : $event->summary;
        $sourceName = $event->source?->sourceFamily?->name ?? $event->source?->name ?? 'Unknown';
        $eventUrl = config('social.urls.event') . '/' . $event->id . ($event->slug ? "-{$event->slug}" : '');
        $emoji = self::CATEGORY_EMOJI[$event->category] ?? "\u{1F534}";

        $sourceLabel = $locale === 'de' ? 'Quelle' : 'Source';

        // Breaking events: replace the category emoji with a red dot + BREAKING/EILMELDUNG label.
        // Applies across all platforms so the live signal is consistent.
        if ($event->isBreaking()) {
            $emoji = "\u{1F534}";
            $breakingLabel = $locale === 'de' ? 'EILMELDUNG' : 'BREAKING';
            $title = "{$breakingLabel}: {$title}";
        }

        if ($platform === 'bluesky') {
            // Bluesky mirrors X: hashtags in the body, no event link (see BlueskyDriver — no link card).
            return $this->buildEventPostWithHashtags($event, $emoji, $title, $summary, self::BLUESKY_MAX_LENGTH);
        }

        if ($platform === 'threads') {
            return $this->buildShortEventPost($emoji, $title, $summary, $sourceLabel, $sourceName, $eventUrl, self::THREADS_MAX_LENGTH);
        }

        if ($platform === 'x') {
            $maxLength = $channel->unlimited_chars ? null : self::X_MAX_LENGTH;
            return $this->buildEventPostWithHashtags($event, $emoji, $title, $summary, $maxLength);
        }

        if ($platform === 'telegram') {
            return $this->buildTelegramEventPost($emoji, $title, $summary, $sourceLabel, $sourceName, $eventUrl);
        }

        // Facebook — generous character limit, use full text
        return "{$emoji} {$title}\n\n{$summary}\n\n{$sourceLabel}: {$sourceName}\n{$eventUrl}";
    }

    public function buildBriefingPost(DailyBriefing $briefing, SocialChannel $channel): string
    {
        $locale = $channel->locale;
        $platform = $channel->platform;
        $date = $briefing->briefing_date->format('Y-m-d');

        $title = $briefing->title;
        $summary = $locale === 'de' ? ($briefing->summary_de ?: $briefing->summary_en) : $briefing->summary_en;
        $briefingUrl = config('social.urls.briefing') . '/' . $date;
        $newsletterUrl = config('social.urls.newsletter');

        if ($locale === 'de') {
            $header = "Tagesbriefing — {$date}";
            $subscribeCta = "Briefing per E-Mail abonnieren: {$newsletterUrl}";
            $fullBriefing = "Vollständiges Briefing: {$briefingUrl}";
        } else {
            $header = "Daily Intelligence Briefing — {$date}";
            $subscribeCta = "Subscribe for daily briefings: {$newsletterUrl}";
            $fullBriefing = "Full briefing: {$briefingUrl}";
        }

        if ($platform === 'bluesky') {
            // Bluesky: briefing URL goes in the embed card
            return $this->buildShortBriefingPost($header, $title, $summary, null, null, self::BLUESKY_MAX_LENGTH);
        }

        if ($platform === 'threads') {
            return $this->buildShortBriefingPost($header, $title, $summary, $subscribeCta, $fullBriefing, self::THREADS_MAX_LENGTH);
        }

        if ($platform === 'telegram') {
            return "<b>{$header}</b>\n\n<b>" . e($title) . "</b>\n\n" . e($summary)
                . "\n\n" . e($subscribeCta) . "\n" . e($fullBriefing);
        }

        if ($platform === 'x') {
            $maxLength = $channel->unlimited_chars ? null : self::X_MAX_LENGTH;
            return $this->buildXBriefingPost($header, $title, $summary, $newsletterUrl, $locale, $maxLength);
        }

        // Facebook
        return "{$header}\n\n{$title}\n\n{$summary}\n\n{$subscribeCta}\n{$fullBriefing}";
    }

    // ── Private: Platform-specific builders ──

    private function buildShortEventPost(
        string $emoji,
        string $title,
        string $summary,
        string $sourceLabel,
        string $sourceName,
        ?string $eventUrl,
        int $maxLength,
    ): string {
        $headerLine = "{$emoji} {$title}";
        $urlSuffix = $eventUrl ? "\n{$eventUrl}" : '';
        $sourceLine = "\n\n{$sourceLabel}: {$sourceName}";

        // 1. Full post with source line
        $withSource = "{$headerLine}\n\n{$summary}{$sourceLine}{$urlSuffix}";
        if (grapheme_strlen($withSource) <= $maxLength) {
            return $withSource;
        }

        // 2. Drop source line first — event is linked anyway
        $withoutSource = "{$headerLine}\n\n{$summary}{$urlSuffix}";
        if (grapheme_strlen($withoutSource) <= $maxLength) {
            return $withoutSource;
        }

        // 3. Truncate summary at word boundary, end with "..."
        $fixedOverhead = grapheme_strlen($headerLine) + grapheme_strlen($urlSuffix) + 2 + 3;
        $available = $maxLength - $fixedOverhead;

        if ($available <= 0) {
            $truncTitle = $this->truncateGraphemes($headerLine, $maxLength - grapheme_strlen($urlSuffix) - 3);

            return $truncTitle . '...' . $urlSuffix;
        }

        $truncatedSummary = $this->truncateAtWordBoundary($summary, $available);

        return "{$headerLine}\n\n{$truncatedSummary}...{$urlSuffix}";
    }

    private function buildShortBriefingPost(
        string $header,
        string $title,
        string $summary,
        ?string $subscribeCta,
        ?string $fullBriefing,
        int $maxLength,
    ): string {
        $footerParts = array_filter([$subscribeCta, $fullBriefing]);
        $footer = $footerParts ? "\n\n" . implode("\n", $footerParts) : '';
        $titleLine = $title;

        $available = $maxLength - grapheme_strlen($header) - grapheme_strlen($titleLine) - grapheme_strlen($footer) - 4;

        if ($available <= 0) {
            $truncHead = $this->truncateGraphemes("{$header}\n\n{$titleLine}", $maxLength - grapheme_strlen($footer) - 5);
            return $truncHead . '...' . $footer;
        }

        $truncatedSummary = $this->truncateAtSentence($summary, $available);

        return "{$header}\n\n{$titleLine}\n\n{$truncatedSummary}{$footer}";
    }

    /**
     * Build a hashtag-style event post (used by X and Bluesky).
     * Pass null for $maxLength to disable truncation (e.g. X Premium accounts).
     */
    private function buildEventPostWithHashtags(Event $event, string $emoji, string $title, string $summary, ?int $maxLength): string
    {
        $hashtags = $this->resolveHashtags($event);
        $hashtagLine = $hashtags ? "\n\n" . implode(' ', $hashtags) : '';

        $headerLine = "{$emoji} {$title}";

        $full = "{$headerLine}\n\n{$summary}{$hashtagLine}";

        if ($maxLength === null || grapheme_strlen($full) <= $maxLength) {
            return $full;
        }

        // Truncate summary, keep hashtags
        $fixedOverhead = grapheme_strlen($headerLine) + grapheme_strlen($hashtagLine) + 2 + 3; // 2 newlines + "..."
        $available = $maxLength - $fixedOverhead;

        if ($available > 20) {
            $truncated = $this->truncateAtSentence($summary, $available);

            return "{$headerLine}\n\n{$truncated}{$hashtagLine}";
        }

        // Drop hashtags, truncate summary
        $fixedOverhead = grapheme_strlen($headerLine) + 2 + 3;
        $available = $maxLength - $fixedOverhead;
        $truncated = $this->truncateAtWordBoundary($summary, $available);

        return "{$headerLine}\n\n{$truncated}...";
    }

    private function buildXBriefingPost(string $header, string $title, string $summary, string $newsletterUrl, string $locale, ?int $maxLength): string
    {
        $cta = $locale === 'de' ? 'Newsletter abonnieren' : 'Subscribe to our newsletter';
        $footer = "\n\n{$cta}: {$newsletterUrl}";

        $full = "{$header}\n\n{$title}\n\n{$summary}{$footer}";

        if ($maxLength === null || grapheme_strlen($full) <= $maxLength) {
            return $full;
        }

        // 2. Truncate summary, keep CTA
        $fixedOverhead = grapheme_strlen($header) + grapheme_strlen("\n\n{$title}") + grapheme_strlen($footer) + 2;
        $available = $maxLength - $fixedOverhead;

        if ($available > 20) {
            $truncated = $this->truncateAtSentence($summary, $available);

            return "{$header}\n\n{$title}\n\n{$truncated}{$footer}";
        }

        // 3. Drop CTA, truncate summary
        $fixedOverhead = grapheme_strlen($header) + grapheme_strlen("\n\n{$title}") + 2 + 3;
        $available = $maxLength - $fixedOverhead;
        $truncated = $this->truncateAtWordBoundary($summary, max($available, 10));

        return "{$header}\n\n{$title}\n\n{$truncated}...";
    }

    /**
     * Resolve hashtags for an event from its conflict thread.
     * Returns up to 3 hashtags or an empty array.
     */
    private function resolveHashtags(Event $event): array
    {
        $thread = $event->conflictThread;
        if (! $thread) {
            // Check parent thread if assigned to a sub-thread
            return [];
        }

        $hashtags = $thread->hashtags ?? [];

        // If sub-thread has no hashtags, inherit from parent
        if (empty($hashtags) && $thread->parent_id) {
            $hashtags = $thread->parent?->hashtags ?? [];
        }

        return array_slice(array_values(array_filter($hashtags)), 0, 3);
    }

    private function buildTelegramEventPost(
        string $emoji,
        string $title,
        string $summary,
        string $sourceLabel,
        string $sourceName,
        string $eventUrl,
    ): string {
        return "{$emoji} <b>" . e($title) . "</b>\n\n"
            . e($summary)
            . "\n\n{$sourceLabel}: " . e($sourceName)
            . "\n<a href=\"{$eventUrl}\">View event</a>";
    }

    /**
     * Truncate text at the nearest sentence boundary within the character limit.
     * Falls back to word boundary if no sentence boundary is found.
     */
    private function truncateAtSentence(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $maxLength);

        // Try to cut at last sentence boundary (. ! ?)
        $lastSentence = max(
            mb_strrpos($truncated, '. ') ?: 0,
            mb_strrpos($truncated, '! ') ?: 0,
            mb_strrpos($truncated, '? ') ?: 0,
        );

        if ($lastSentence > $maxLength * 0.4) {
            return mb_substr($truncated, 0, $lastSentence + 1);
        }

        // Fall back to word boundary
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace > $maxLength * 0.4) {
            return mb_substr($truncated, 0, $lastSpace) . '...';
        }

        return mb_substr($truncated, 0, $maxLength - 3) . '...';
    }

    private function truncateAtWordBoundary(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return rtrim($text);
        }

        $truncated = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false && $lastSpace > $maxLength * 0.5) {
            return rtrim(mb_substr($truncated, 0, $lastSpace));
        }

        return rtrim($truncated);
    }

    private function truncateGraphemes(string $text, int $maxGraphemes): string
    {
        if (grapheme_strlen($text) <= $maxGraphemes) {
            return $text;
        }

        return grapheme_substr($text, 0, $maxGraphemes);
    }
}
