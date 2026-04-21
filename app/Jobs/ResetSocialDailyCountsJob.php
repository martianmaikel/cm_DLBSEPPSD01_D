<?php

namespace App\Jobs;

use App\Models\SocialChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ResetSocialDailyCountsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        SocialChannel::query()->update(['daily_post_count' => 0]);
    }
}
