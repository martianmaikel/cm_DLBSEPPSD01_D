<?php

namespace App\Jobs;

use App\Contracts\SocialDriver;
use App\Models\SocialChannel;
use App\Models\SocialPost;
use App\Services\Social\ContentBuilder;
use App\Services\Social\Drivers\BlueskyDriver;
use App\Services\Social\Drivers\FacebookDriver;
use App\Services\Social\Drivers\TelegramDriver;
use App\Services\Social\Drivers\ThreadsDriver;
use App\Services\Social\Drivers\XDriver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PublishSocialPostJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180; // video uploads can take 60s+ for download + chunked upload + processing

    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $socialChannelId,
        public string $postableType,
        public string $postableId,
    ) {
        $this->onQueue('social');
    }

    public function handle(ContentBuilder $contentBuilder): void
    {
        $channel = SocialChannel::find($this->socialChannelId);
        if (! $channel || ! $channel->enabled) {
            return;
        }

        // Throttle: re-queue with delay if last post was too recent
        $interval = $channel->min_post_interval ?? 60;
        if ($interval > 0 && $channel->last_posted_at) {
            $secondsSinceLast = (int) $channel->last_posted_at->diffInSeconds(now());
            if ($secondsSinceLast < $interval) {
                $delay = $interval - $secondsSinceLast;
                self::dispatch($this->socialChannelId, $this->postableType, $this->postableId)
                    ->delay(now()->addSeconds($delay));

                return;
            }
        }

        $postable = $this->postableType::find($this->postableId);
        if (! $postable) {
            return;
        }

        $postKey = $this->buildPostKey();

        // Idempotency guard via unique post_key
        try {
            $post = SocialPost::create([
                'social_channel_id' => $channel->id,
                'postable_type' => $this->postableType,
                'postable_id' => $this->postableId,
                'post_key' => $postKey,
                'platform' => $channel->platform,
                'locale' => $channel->locale,
                'status' => 'queued',
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), '23505') || str_contains(strtolower($e->getMessage()), 'unique')) {
                return; // Already posted
            }
            throw $e;
        }

        // Build content based on postable type
        $text = $this->buildContent($contentBuilder, $postable, $channel);
        $link = $this->buildLink($postable);
        $media = $this->resolveMedia($postable);
        $post->update(['content_text' => $text]);

        // Resolve the driver for this platform
        $driver = $this->resolveDriver($channel->platform);

        $result = $driver->publish($channel, $text, $link, $media);

        if ($result->success) {
            $post->update([
                'platform_post_id' => $result->platformPostId,
                'status' => 'published',
                'published_at' => now(),
            ]);
            $channel->incrementPostCount();

            Log::info('SocialPost published', [
                'post_id' => $post->id,
                'channel' => $channel->name,
                'platform' => $channel->platform,
                'postable' => $this->postableType . ':' . $this->postableId,
            ]);
            return;
        }

        // Handle token expiry — attempt refresh and retry once
        if ($result->error && str_starts_with($result->error, 'TOKEN_EXPIRED:')) {
            if ($driver->refreshToken($channel)) {
                $channel->refresh();
                $retryResult = $driver->publish($channel, $text, $link, $media);

                if ($retryResult->success) {
                    $post->update([
                        'platform_post_id' => $retryResult->platformPostId,
                        'status' => 'published',
                        'published_at' => now(),
                    ]);
                    $channel->incrementPostCount();
                    return;
                }

                $result = $retryResult;
            }
        }

        // Mark as failed
        $post->update([
            'status' => 'failed',
            'error' => mb_substr($result->error ?? 'Unknown error', 0, 1000),
        ]);

        Log::error('SocialPost failed', [
            'post_id' => $post->id,
            'channel' => $channel->name,
            'platform' => $channel->platform,
            'error' => $result->error,
        ]);
    }

    private function buildPostKey(): string
    {
        $typeShort = match ($this->postableType) {
            \App\Models\Event::class => 'event',
            \App\Models\DailyBriefing::class => 'briefing',
            default => 'unknown',
        };

        return "{$typeShort}:{$this->postableId}:channel:{$this->socialChannelId}";
    }

    private function buildContent(ContentBuilder $contentBuilder, mixed $postable, SocialChannel $channel): string
    {
        if ($postable instanceof \App\Models\Event) {
            return $contentBuilder->buildEventPost($postable, $channel);
        }

        if ($postable instanceof \App\Models\DailyBriefing) {
            return $contentBuilder->buildBriefingPost($postable, $channel);
        }

        return '';
    }

    private function buildLink(mixed $postable): ?string
    {
        if ($postable instanceof \App\Models\Event) {
            return config('social.urls.event') . '/' . $postable->id;
        }

        if ($postable instanceof \App\Models\DailyBriefing) {
            return config('social.urls.briefing') . '/' . $postable->briefing_date->format('Y-m-d');
        }

        return null;
    }

    /**
     * Resolve media URLs from the postable (Events only).
     * Only returns items with a direct URL (not telegram_file_id).
     */
    private function resolveMedia(mixed $postable): ?array
    {
        if (! $postable instanceof \App\Models\Event) {
            return null;
        }

        $mediaUrls = $postable->media_urls;
        if (empty($mediaUrls) || ! is_array($mediaUrls)) {
            return null;
        }

        // Only pass items that have a direct URL (not Telegram file_ids)
        $directMedia = array_filter($mediaUrls, fn ($m) => ! empty($m['url']));

        return ! empty($directMedia) ? array_values($directMedia) : null;
    }

    private function resolveDriver(string $platform): SocialDriver
    {
        return match ($platform) {
            'telegram' => new TelegramDriver,
            'facebook' => new FacebookDriver,
            'threads' => new ThreadsDriver,
            'bluesky' => new BlueskyDriver,
            'x' => new XDriver,
            default => throw new \RuntimeException("Unknown social platform: {$platform}"),
        };
    }
}
