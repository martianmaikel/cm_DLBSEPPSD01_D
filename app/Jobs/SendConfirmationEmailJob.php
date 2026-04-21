<?php

namespace App\Jobs;

use App\Mail\ConfirmSubscriptionMail;
use App\Models\NewsletterSend;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendConfirmationEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public string $subscriberId) {}

    public function handle(): void
    {
        $subscriber = NewsletterSubscriber::find($this->subscriberId);

        if (! $subscriber) {
            Log::warning('SendConfirmationEmailJob: subscriber not found', ['id' => $this->subscriberId]);
            return;
        }

        if ($subscriber->status !== 'pending') {
            // Already confirmed or unsubscribed — don't re-send
            return;
        }

        $mail = new ConfirmSubscriptionMail($subscriber);
        Mail::send($mail);

        NewsletterSend::create([
            'subscriber_id' => $subscriber->id,
            'type' => 'confirm',
            'send_key' => 'confirm:'.$subscriber->id.':'.now()->timestamp,
            'subject' => $mail->envelope()->subject,
            'locale' => $subscriber->locale,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
