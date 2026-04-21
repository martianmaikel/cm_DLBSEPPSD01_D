<?php

namespace App\Jobs;

use App\Models\DailyBriefing;
use App\Models\SocialChannel;
use App\Models\SocialPost;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DispatchBriefingSocialPostsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 15;

    public function __construct(public ?string $date = null) {}

    public function handle(): void
    {
        if (! config('social.enabled')) {
            return;
        }

        $briefing = $this->date
            ? DailyBriefing::forDate(now()->parse($this->date))->first()
            : DailyBriefing::latest()->first();

        if (! $briefing) {
            Log::info('DispatchBriefingSocialPostsJob: no briefing found', ['date' => $this->date]);
            return;
        }

        $channels = SocialChannel::enabled()->postsBriefings()->get();

        if ($channels->isEmpty()) {
            return;
        }

        $dispatched = 0;

        foreach ($channels as $channel) {
            $postKey = "briefing:{$briefing->id}:channel:{$channel->id}";
            if (SocialPost::where('post_key', $postKey)->exists()) {
                continue;
            }

            PublishSocialPostJob::dispatch($channel->id, DailyBriefing::class, $briefing->id);
            $dispatched++;
        }

        if ($dispatched > 0) {
            Log::info('DispatchBriefingSocialPostsJob: dispatched', [
                'briefing_id' => $briefing->id,
                'briefing_date' => $briefing->briefing_date->toDateString(),
                'channels' => $dispatched,
            ]);
        }
    }
}
