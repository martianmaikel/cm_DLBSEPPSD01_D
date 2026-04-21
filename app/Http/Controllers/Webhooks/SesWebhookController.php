<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SesWebhookController extends Controller
{
    /**
     * Handle incoming SNS notifications from AWS SES.
     *
     * NOTE: In production, SNS message signatures MUST be validated using
     * Aws\Sns\MessageValidator from `composer require aws/aws-sdk-php`.
     * This controller currently logs unverified payloads — safe only while
     * the endpoint is not publicly linked to a production SNS topic.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->all();
        $type = $request->header('x-amz-sns-message-type') ?? ($payload['Type'] ?? null);

        if (! $type) {
            return response('Missing SNS type', 400);
        }

        // TODO (production): validate SNS signature here before trusting payload.
        // $validator = new \Aws\Sns\MessageValidator();
        // if (! $validator->isValid(\Aws\Sns\Message::fromRawPostData())) { return response('Invalid signature', 403); }

        return match ($type) {
            'SubscriptionConfirmation' => $this->confirmSubscription($payload),
            'Notification' => $this->handleNotification($payload),
            'UnsubscribeConfirmation' => response('OK', 200),
            default => response('Unsupported type', 400),
        };
    }

    private function confirmSubscription(array $payload): Response
    {
        $subscribeUrl = $payload['SubscribeURL'] ?? null;
        if (! $subscribeUrl) {
            return response('Missing SubscribeURL', 400);
        }

        // Hit the SubscribeURL to confirm the SNS subscription
        Http::timeout(10)->get($subscribeUrl);

        Log::info('SES SNS subscription confirmed', ['topic' => $payload['TopicArn'] ?? null]);

        DB::table('newsletter_ses_events')->insert([
            'message_id' => $payload['MessageId'] ?? null,
            'event_type' => 'SubscriptionConfirmation',
            'recipient_email' => null,
            'payload' => json_encode($payload),
            'received_at' => now(),
        ]);

        return response('Subscription confirmed', 200);
    }

    private function handleNotification(array $payload): Response
    {
        // The Message field is a JSON string with the actual SES notification
        $messageRaw = $payload['Message'] ?? '{}';
        $message = is_array($messageRaw) ? $messageRaw : json_decode($messageRaw, true);

        if (! is_array($message)) {
            return response('Malformed Message', 400);
        }

        $notificationType = $message['notificationType'] ?? $message['eventType'] ?? 'Unknown';
        $recipients = $this->extractRecipients($message, $notificationType);

        // Log the event once per recipient (or once if no recipient)
        foreach ($recipients ?: [null] as $recipient) {
            DB::table('newsletter_ses_events')->insert([
                'message_id' => $payload['MessageId'] ?? ($message['mail']['messageId'] ?? null),
                'event_type' => $notificationType,
                'recipient_email' => $recipient,
                'payload' => json_encode($message),
                'received_at' => now(),
            ]);
        }

        // Update subscriber statuses for hard events
        if ($notificationType === 'Bounce') {
            $this->handleBounce($message, $recipients);
        } elseif ($notificationType === 'Complaint') {
            $this->handleComplaint($recipients);
        }

        return response('OK', 200);
    }

    private function extractRecipients(array $message, string $type): array
    {
        return match ($type) {
            'Bounce' => array_column($message['bounce']['bouncedRecipients'] ?? [], 'emailAddress'),
            'Complaint' => array_column($message['complaint']['complainedRecipients'] ?? [], 'emailAddress'),
            'Delivery' => $message['delivery']['recipients'] ?? [],
            default => [],
        };
    }

    private function handleBounce(array $message, array $recipients): void
    {
        $bounceType = $message['bounce']['bounceType'] ?? 'Undetermined';
        $isHard = $bounceType === 'Permanent';

        foreach ($recipients as $email) {
            $subscriber = NewsletterSubscriber::where('email', strtolower($email))->first();
            if (! $subscriber) continue;

            $subscriber->increment('bounce_count');

            if ($isHard || $subscriber->bounce_count >= 3) {
                $subscriber->update(['status' => 'bounced']);
            }
        }
    }

    private function handleComplaint(array $recipients): void
    {
        foreach ($recipients as $email) {
            NewsletterSubscriber::where('email', strtolower($email))
                ->update([
                    'status' => 'complained',
                    'unsubscribed_at' => now(),
                ]);
        }
    }
}
