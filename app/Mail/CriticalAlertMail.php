<?php

namespace App\Mail;

use App\Models\ConflictThread;
use App\Models\Event;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class CriticalAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public NewsletterSubscriber $subscriber,
        public Event $event,
        public ConflictThread $thread,
    ) {}

    public function envelope(): Envelope
    {
        app()->setLocale($this->subscriber->locale);

        return new Envelope(
            from: new Address(
                config('services.newsletter.from_address'),
                config('services.newsletter.from_name'),
            ),
            to: [new Address($this->subscriber->email)],
            subject: __('newsletter.critical.subject', [
                'thread' => $this->thread->name,
                'severity' => $this->event->severity,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.critical-alert',
            with: [
                'locale' => $this->subscriber->locale,
                'thread_name' => $this->thread->name,
                'thread_url' => url('/thread/'.$this->thread->id),
                'event' => [
                    'title' => $this->event->title,
                    'summary' => $this->event->summary,
                    'severity' => $this->event->severity,
                    'confidence' => $this->event->confidence,
                    'status' => $this->event->status,
                    'country' => $this->event->country,
                    'category' => $this->event->category,
                    'source_url' => $this->event->source_url,
                ],
                'event_url' => url('/event/'.$this->event->id),
                'unsubscribe_url' => url('/newsletter/unsubscribe/'.$this->subscriber->unsubscribe_token),
                'preferences_url' => url('/newsletter/preferences/'.$this->subscriber->preferences_token),
            ],
        );
    }

    public function headers(): Headers
    {
        $unsubscribeUrl = url('/newsletter/unsubscribe/'.$this->subscriber->unsubscribe_token);
        $mailbox = config('services.newsletter.unsubscribe_mailbox');

        return new Headers(
            text: [
                'List-Unsubscribe' => "<mailto:{$mailbox}?subject={$this->subscriber->unsubscribe_token}>, <{$unsubscribeUrl}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }
}
