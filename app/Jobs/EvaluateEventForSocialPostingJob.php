<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\SocialChannel;
use App\Models\SocialPost;
use App\Services\Social\RelevanceScorer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EvaluateEventForSocialPostingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 15;

    public function __construct(public string $eventId) {}

    public function handle(RelevanceScorer $scorer): void
    {
        if (! config('social.enabled')) {
            return;
        }

        $event = Event::with('source.sourceFamily')->find($this->eventId);
        if (! $event) {
            return;
        }

        if (! $scorer->isRelevant($event)) {
            return;
        }

        $channels = SocialChannel::enabled()->postsEvents()->get();

        if ($channels->isEmpty()) {
            return;
        }

        $dispatched = 0;

        foreach ($channels as $channel) {
            // Check daily limit
            if (! $channel->isUnderDailyLimit()) {
                continue;
            }

            // Check idempotency — don't dispatch if already posted
            $postKey = "event:{$event->id}:channel:{$channel->id}";
            if (SocialPost::where('post_key', $postKey)->exists()) {
                continue;
            }

            PublishSocialPostJob::dispatch($channel->id, Event::class, $event->id);
            $dispatched++;
        }

        if ($dispatched > 0) {
            Log::info('EvaluateEventForSocialPostingJob: dispatched', [
                'event_id' => $event->id,
                'severity' => $event->severity,
                'weighted_severity' => $scorer->weightedSeverity($event),
                'channels' => $dispatched,
            ]);
        }
    }
}
