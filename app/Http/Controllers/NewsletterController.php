<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\SeoMeta;
use App\Http\Requests\SubscribeRequest;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Jobs\SendConfirmationEmailJob;
use App\Models\ConflictThread;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterController extends Controller
{
    public function subscribeForm(): Response
    {
        request()->attributes->set('seo', SeoMeta::make(
            title: __('seo.newsletter.title'),
            description: __('seo.newsletter.description'),
            canonical: url('/newsletter'),
            ogImage: url('/images/og-banner.jpg'),
            twitterCard: 'summary_large_image',
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Newsletter'],
            ],
        ));

        return Inertia::render('Newsletter/Subscribe', [
            'threads' => $this->availableThreads(),
        ]);
    }

    public function subscribe(SubscribeRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $email = strtolower(trim($validated['email']));
        $threadIds = $validated['thread_ids'] ?? [];

        $existing = NewsletterSubscriber::where('email', $email)->first();

        if ($existing) {
            // Resend confirmation if still pending
            if ($existing->status === 'pending') {
                $this->syncThreads($existing, $threadIds);
                SendConfirmationEmailJob::dispatch($existing->id)->afterCommit();
            } elseif ($existing->status === 'unsubscribed') {
                // Re-activate: reset to pending with fresh token
                $existing->update([
                    'status' => 'pending',
                    'confirm_token' => \Illuminate\Support\Str::random(64),
                    'timezone' => $validated['timezone'],
                    'locale' => $validated['locale'],
                    'unsubscribed_at' => null,
                ]);
                $this->syncThreads($existing, $threadIds);
                SendConfirmationEmailJob::dispatch($existing->id)->afterCommit();
            }
            // For confirmed/bounced/complained: silently succeed (avoid email enumeration)

            return redirect()->route('newsletter.subscribed')->with('email', $email);
        }

        $subscriber = NewsletterSubscriber::createPending([
            'email' => $email,
            'timezone' => $validated['timezone'],
            'locale' => $validated['locale'],
            'confirm_ip' => $request->ip(),
        ]);

        $this->syncThreads($subscriber, $threadIds);

        SendConfirmationEmailJob::dispatch($subscriber->id)->afterCommit();

        return redirect()->route('newsletter.subscribed')->with('email', $email);
    }

    private function availableThreads(): array
    {
        return ConflictThread::query()
            ->open()
            ->topLevel()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'summary', 'event_count_total', 'max_severity'])
            ->toArray();
    }

    /**
     * Attach threads to a subscriber with default digest+critical enabled.
     */
    private function syncThreads(NewsletterSubscriber $subscriber, array $threadIds): void
    {
        if (empty($threadIds)) {
            $subscriber->threads()->detach();
            return;
        }

        $sync = [];
        foreach ($threadIds as $id) {
            $sync[(int) $id] = [
                'wants_digest' => true,
                'wants_critical' => true,
            ];
        }
        $subscriber->threads()->sync($sync);
    }

    public function subscribed(Request $request): Response
    {
        return Inertia::render('Newsletter/Subscribed', [
            'email' => $request->session()->get('email'),
        ]);
    }

    public function confirm(string $token): Response
    {
        request()->attributes->set('seo', SeoMeta::make(
            title: 'Confirm Subscription',
            robots: 'noindex,nofollow',
        ));

        $subscriber = NewsletterSubscriber::where('confirm_token', $token)->first();

        if (! $subscriber) {
            return Inertia::render('Newsletter/InvalidToken');
        }

        if ($subscriber->status === 'pending') {
            $subscriber->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirm_token' => null, // burn the token
            ]);
        }

        return Inertia::render('Newsletter/Confirmed', [
            'timezone' => $subscriber->timezone,
        ]);
    }

    public function preferences(string $token): Response
    {
        request()->attributes->set('seo', SeoMeta::make(
            title: 'Newsletter Preferences',
            robots: 'noindex,nofollow',
        ));

        $subscriber = NewsletterSubscriber::where('preferences_token', $token)->first();

        if (! $subscriber || $subscriber->status === 'unsubscribed') {
            return Inertia::render('Newsletter/InvalidToken');
        }

        $subscribedThreadIds = $subscriber->threads()->pluck('conflict_threads.id')->toArray();
        $subscribedPrefs = $subscriber->threads->keyBy('id')->map(fn ($t) => [
            'wants_digest' => (bool) $t->pivot->wants_digest,
            'wants_critical' => (bool) $t->pivot->wants_critical,
        ])->toArray();

        return Inertia::render('Newsletter/Preferences', [
            'subscriber' => [
                'email' => $subscriber->email,
                'timezone' => $subscriber->timezone,
                'locale' => $subscriber->locale,
                'wants_global_digest' => $subscriber->wants_global_digest,
                'unsubscribe_token' => $subscriber->unsubscribe_token,
            ],
            'threads' => $this->availableThreads(),
            'subscribed_thread_ids' => $subscribedThreadIds,
            'thread_prefs' => $subscribedPrefs,
        ]);
    }

    public function updatePreferences(UpdatePreferencesRequest $request, string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::where('preferences_token', $token)->first();

        if (! $subscriber || $subscriber->status === 'unsubscribed') {
            return redirect()->route('newsletter.form');
        }

        $validated = $request->validated();

        $subscriber->update([
            'timezone' => $validated['timezone'],
            'locale' => $validated['locale'],
            'wants_global_digest' => (bool) $validated['wants_global_digest'],
        ]);

        $sync = [];
        foreach ($validated['threads'] ?? [] as $thread) {
            $sync[(int) $thread['id']] = [
                'wants_digest' => (bool) $thread['wants_digest'],
                'wants_critical' => (bool) $thread['wants_critical'],
            ];
        }
        $subscriber->threads()->sync($sync);

        return back()->with('success', 'saved');
    }

    public function unsubscribeForm(string $token): Response
    {
        request()->attributes->set('seo', SeoMeta::make(
            title: 'Unsubscribe',
            robots: 'noindex,nofollow',
        ));

        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)->first();

        if (! $subscriber) {
            return Inertia::render('Newsletter/InvalidToken');
        }

        $this->markUnsubscribed($subscriber);

        return Inertia::render('Newsletter/Unsubscribed', [
            'email' => $subscriber->email,
        ]);
    }

    /**
     * RFC 8058 one-click POST unsubscribe (List-Unsubscribe-Post).
     */
    public function unsubscribePost(string $token): \Illuminate\Http\Response
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)->first();

        if (! $subscriber) {
            return response('Not found', 404);
        }

        $this->markUnsubscribed($subscriber);

        return response('OK', 200);
    }

    private function markUnsubscribed(NewsletterSubscriber $subscriber): void
    {
        if ($subscriber->status !== 'unsubscribed') {
            $subscriber->update([
                'status' => 'unsubscribed',
                'unsubscribed_at' => now(),
            ]);
        }
    }
}
