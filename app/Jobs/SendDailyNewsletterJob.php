<?php

namespace App\Jobs;

use App\Mail\DailyGlobalBriefingMail;
use App\Models\NewsletterSend;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDailyNewsletterJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public string $subscriberId,
        public string $localDate, // YYYY-MM-DD in subscriber's timezone
    ) {
        $this->onQueue('newsletter');
    }

    public function handle(): void
    {
        $subscriber = NewsletterSubscriber::find($this->subscriberId);

        if (! $subscriber || $subscriber->status !== 'confirmed') {
            return;
        }

        if (! $subscriber->wants_global_digest) {
            return;
        }

        $sendKey = 'daily_global:'.$subscriber->id.':'.$this->localDate;

        // Idempotency guard: create the log row first. If it already exists, skip.
        try {
            $log = NewsletterSend::create([
                'subscriber_id' => $subscriber->id,
                'type' => 'daily_global',
                'send_key' => $sendKey,
                'locale' => $subscriber->locale,
                'status' => 'queued',
            ]);
        } catch (QueryException $e) {
            // Unique constraint on send_key → already dispatched today
            if ($this->isUniqueViolation($e)) {
                Log::info('SendDailyNewsletterJob: already sent', ['send_key' => $sendKey]);
                return;
            }
            throw $e;
        }

        try {
            $targetDate = Carbon::createFromFormat('Y-m-d', $this->localDate, $subscriber->timezone);
            $mail = new DailyGlobalBriefingMail($subscriber, $targetDate);
            Mail::send($mail);

            $log->update([
                'status' => 'sent',
                'subject' => $mail->envelope()->subject,
                'sent_at' => now(),
            ]);

            $subscriber->update(['last_sent_at' => now()]);
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error' => substr($e->getMessage(), 0, 500),
            ]);
            Log::error('SendDailyNewsletterJob failed', [
                'subscriber_id' => $subscriber->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // PostgreSQL SQLSTATE 23505 = unique_violation
        return str_contains($e->getMessage(), '23505')
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
