<?php

namespace App\Console\Commands;

use App\Models\SocialChannel;
use App\Services\Social\Drivers\FacebookDriver;
use App\Services\Social\Drivers\ThreadsDriver;
use Illuminate\Console\Command;

class RefreshMetaTokensCommand extends Command
{
    protected $signature = 'social:refresh-meta-tokens {--force : Refresh all Meta tokens regardless of expiry}';

    protected $description = 'Refresh expiring Meta (Threads/Facebook) access tokens';

    public function handle(): int
    {
        $daysBeforeExpiry = config('social.meta.token_refresh_days_before_expiry', 14);
        $threshold = now()->addDays($daysBeforeExpiry);

        $query = SocialChannel::enabled()
            ->whereIn('platform', ['threads', 'facebook']);

        if (! $this->option('force')) {
            $query->where(function ($q) use ($threshold) {
                $q->whereNotNull('token_expires_at')
                  ->where('token_expires_at', '<=', $threshold);
            });
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->info('No Meta tokens need refreshing.');
            return self::SUCCESS;
        }

        $refreshed = 0;
        $failed = 0;

        foreach ($channels as $channel) {
            $driver = match ($channel->platform) {
                'threads' => new ThreadsDriver,
                'facebook' => new FacebookDriver,
            };

            $this->info("Refreshing token for {$channel->name} ({$channel->platform})...");

            if ($driver->refreshToken($channel)) {
                $refreshed++;
                $this->info("  Refreshed successfully.");
            } else {
                $failed++;
                $this->error("  Refresh FAILED.");
            }
        }

        $this->info("Done: {$refreshed} refreshed, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
