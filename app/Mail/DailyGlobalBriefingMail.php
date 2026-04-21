<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\BuildDailyNewsletterContent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class DailyGlobalBriefingMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Pre-built content array (see BuildDailyNewsletterContent::build()).
     */
    protected array $payload;

    public function __construct(
        public NewsletterSubscriber $subscriber,
        public ?Carbon $date = null,
    ) {
        $this->payload = app(BuildDailyNewsletterContent::class)->build($subscriber, $date);
    }

    public function envelope(): Envelope
    {
        app()->setLocale($this->subscriber->locale);

        return new Envelope(
            from: new Address(
                config('services.newsletter.from_address'),
                config('services.newsletter.from_name'),
            ),
            to: [new Address($this->subscriber->email)],
            subject: $this->payload['subject'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-global',
            with: $this->payload,
        );
    }

    public function headers(): Headers
    {
        $mailbox = config('services.newsletter.unsubscribe_mailbox');

        return new Headers(
            text: [
                'List-Unsubscribe' => "<mailto:{$mailbox}?subject={$this->subscriber->unsubscribe_token}>, <{$this->payload['unsubscribe_url']}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
