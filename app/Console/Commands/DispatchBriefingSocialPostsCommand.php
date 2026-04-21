<?php

namespace App\Console\Commands;

use App\Jobs\DispatchBriefingSocialPostsJob;
use Illuminate\Console\Command;

class DispatchBriefingSocialPostsCommand extends Command
{
    protected $signature = 'social:dispatch-briefing-posts {--date= : Specific date (Y-m-d), defaults to today}';

    protected $description = 'Dispatch social media posts for the daily briefing';

    public function handle(): int
    {
        if (! config('social.enabled')) {
            $this->warn('Social posting is disabled (SOCIAL_POSTING_ENABLED=false).');
            return self::SUCCESS;
        }

        $date = $this->option('date');

        DispatchBriefingSocialPostsJob::dispatch($date);

        $this->info('Briefing social posts dispatch job queued' . ($date ? " for {$date}" : '') . '.');

        return self::SUCCESS;
    }
}
