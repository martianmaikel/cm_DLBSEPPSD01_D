<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyNewsletterJob;
use App\Models\NewsletterSend;
use App\Models\NewsletterSubscriber;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DispatchHourlyNewsletterCommand extends Command
{
    protected $signature = 'newsletter:dispatch-hourly
        {--dry-run : Show what would be sent without dispatching}
        {--force-hour= : Override the target local hour (default: 7) for testing}
        {--subscriber= : Dispatch to a single subscriber ID (bypasses timezone check)}';

    protected $description = 'Dispatches the daily global briefing to subscribers where it is currently 07:00 local time';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $targetHour = (int) ($this->option('force-hour') ?? 7);
        $singleId = $this->option('subscriber');

        if ($singleId) {
            return $this->dispatchSingle($singleId, $dryRun);
        }

        $timezones = NewsletterSubscriber::query()
            ->confirmed()
            ->where('wants_global_digest', true)
            ->distinct()
            ->pluck('timezone')
            ->filter()
            ->toArray();

        if (empty($timezones)) {
            $this->info('No confirmed subscribers to dispatch to.');
            return self::SUCCESS;
        }

        $dispatchedCount = 0;
        $skippedCount = 0;
        $matchingTimezones = [];

        foreach ($timezones as $tz) {
            try {
                $localNow = Carbon::now($tz);
            } catch (\Throwable $e) {
                $this->warn("Invalid timezone '{$tz}' — skipping");
                continue;
            }

            if ($localNow->hour !== $targetHour) {
                continue;
            }

            $matchingTimezones[] = $tz;
            $localDate = $localNow->format('Y-m-d');

            $subscribers = NewsletterSubscriber::query()
                ->confirmed()
                ->where('wants_global_digest', true)
                ->byTimezone($tz)
                ->get();

            foreach ($subscribers as $subscriber) {
                $sendKey = 'daily_global:'.$subscriber->id.':'.$localDate;

                if (NewsletterSend::where('send_key', $sendKey)->exists()) {
                    $skippedCount++;
                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '  [DRY] %s <%s> %s @ %s',
                        $subscriber->id,
                        $subscriber->email,
                        $tz,
                        $localNow->format('H:i')
                    ));
                } else {
                    SendDailyNewsletterJob::dispatch($subscriber->id, $localDate);
                }
                $dispatchedCount++;
            }
        }

        if (empty($matchingTimezones)) {
            $this->info("No timezones currently at {$targetHour}:00 — nothing to do.");
            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->info(sprintf(
            '%sDispatched %d jobs (skipped %d already-sent) across %d matching timezone(s): %s',
            $prefix,
            $dispatchedCount,
            $skippedCount,
            count($matchingTimezones),
            implode(', ', $matchingTimezones),
        ));

        return self::SUCCESS;
    }

    private function dispatchSingle(string $subscriberId, bool $dryRun): int
    {
        $subscriber = NewsletterSubscriber::find($subscriberId);

        if (! $subscriber) {
            $this->error("Subscriber {$subscriberId} not found.");
            return self::FAILURE;
        }

        $localDate = Carbon::now($subscriber->timezone)->format('Y-m-d');

        if ($dryRun) {
            $this->info("[DRY] Would dispatch to {$subscriber->email} (timezone {$subscriber->timezone}, date {$localDate})");
            return self::SUCCESS;
        }

        SendDailyNewsletterJob::dispatch($subscriber->id, $localDate);
        $this->info("Dispatched daily briefing to {$subscriber->email}");

        return self::SUCCESS;
    }
}
