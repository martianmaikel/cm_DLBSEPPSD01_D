<?php

namespace App\Jobs;

use App\Mail\CriticalAlertMail;
use App\Models\ConflictThread;
use App\Models\Event;
use App\Models\NewsletterSend;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCriticalAlertJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(
        public string $subscriberId,
        public string $eventId,
    ) {
        $this->onQueue('newsletter');
    }

    public function handle(): void
    {
        $subscriber = NewsletterSubscriber::find($this->subscriberId);
        if (! $subscriber || $subscriber->status !== 'confirmed') {
            return;
        }

        $event = Event::find($this->eventId);
        if (! $event || ! $event->conflict_thread_id) {
            return;
        }

        $thread = ConflictThread::find($event->conflict_thread_id);
        if (! $thread) {
            return;
        }

        $sendKey = 'critical:'.$this->eventId.':'.$this->subscriberId;

        // Idempotency: unique send_key prevents duplicates for same event+subscriber
        try {
            $log = NewsletterSend::create([
                'subscriber_id' => $subscriber->id,
                'type' => 'critical_alert',
                'send_key' => $sendKey,
                'locale' => $subscriber->locale,
                'status' => 'queued',
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), '23505') || str_contains(strtolower($e->getMessage()), 'unique')) {
                return;
            }
            throw $e;
        }

        try {
            $mail = new CriticalAlertMail($subscriber, $event, $thread);
            Mail::send($mail);

            $log->update([
                'status' => 'sent',
                'subject' => $mail->envelope()->subject,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error' => substr($e->getMessage(), 0, 500),
            ]);
            Log::error('SendCriticalAlertJob failed', [
                'subscriber_id' => $subscriber->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
