<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class ConfirmSubscriptionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public NewsletterSubscriber $subscriber,
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
            subject: __('newsletter.confirm.subject'),
        );
    }

    public function content(): Content
    {
        $confirmUrl = url('/newsletter/confirm/'.$this->subscriber->confirm_token);
        $unsubscribeUrl = url('/newsletter/unsubscribe/'.$this->subscriber->unsubscribe_token);

        return new Content(
            view: 'emails.confirm',
            with: [
                'locale' => $this->subscriber->locale,
                'confirmUrl' => $confirmUrl,
                'unsubscribeUrl' => $unsubscribeUrl,
            ],
        );
    }

    /**
     * RFC 8058 one-click unsubscribe headers.
     */
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
