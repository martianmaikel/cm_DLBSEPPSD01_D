<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchCriticalAlertJob;
use App\Jobs\SendDailyNewsletterJob;
use App\Mail\CriticalAlertMail;
use App\Mail\DailyGlobalBriefingMail;
use App\Mail\TestNewsletterMail;
use App\Models\ConflictThread;
use App\Models\Event;
use App\Models\NewsletterSend;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class AdminSubscriberController extends Controller
{
    public function index(Request $request): Response
    {
        $query = NewsletterSubscriber::query();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('locale')) {
            $query->where('locale', $request->string('locale')->toString());
        }

        if ($request->filled('search')) {
            $query->where('email', 'ILIKE', '%'.$request->string('search')->toString().'%');
        }

        $subscribers = $query
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $statusCounts = NewsletterSubscriber::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return Inertia::render('Admin/Subscribers', [
            'subscribers' => $subscribers,
            'filters' => $request->only(['status', 'locale', 'search']),
            'statusCounts' => $statusCounts,
            'alertsPaused' => (bool) Cache::get(DispatchCriticalAlertJob::PAUSE_CACHE_KEY),
        ]);
    }

    public function toggleAlerts(): RedirectResponse
    {
        $key = DispatchCriticalAlertJob::PAUSE_CACHE_KEY;
        $currentlyPaused = (bool) Cache::get($key);

        if ($currentlyPaused) {
            Cache::forget($key);
            $msg = 'Critical alerts resumed.';
        } else {
            Cache::forever($key, true);
            $msg = 'Critical alerts paused globally.';
        }

        return back()->with('success', $msg);
    }

    public function show(NewsletterSubscriber $subscriber): Response
    {
        $sends = $subscriber->sends()
            ->orderByDesc('sent_at')
            ->limit(50)
            ->get();

        return Inertia::render('Admin/SubscriberDetail', [
            'subscriber' => $subscriber,
            'sends' => $sends,
        ]);
    }

    public function sendTest(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $mail = new TestNewsletterMail($subscriber);
        Mail::send($mail);

        NewsletterSend::create([
            'subscriber_id' => $subscriber->id,
            'type' => 'test',
            'send_key' => 'test:'.$subscriber->id.':'.now()->timestamp,
            'subject' => $mail->envelope()->subject,
            'locale' => $subscriber->locale,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return back()->with('success', 'Test email sent to '.$subscriber->email);
    }

    public function sendDaily(NewsletterSubscriber $subscriber): RedirectResponse
    {
        if ($subscriber->status !== 'confirmed') {
            return back()->with('error', 'Subscriber is not confirmed — cannot send daily briefing.');
        }

        $localDate = Carbon::now($subscriber->timezone)->format('Y-m-d');

        SendDailyNewsletterJob::dispatch($subscriber->id, $localDate);

        return back()->with('success', 'Daily briefing queued for '.$subscriber->email.' (date: '.$localDate.')');
    }

    public function previewDaily(NewsletterSubscriber $subscriber): \Illuminate\Http\Response
    {
        $mail = new DailyGlobalBriefingMail($subscriber);
        return response($mail->render(), 200, ['Content-Type' => 'text/html']);
    }

    public function previewCritical(NewsletterSubscriber $subscriber): \Illuminate\Http\Response
    {
        // Pick the most recent SEV>=9 event from one of the subscriber's subscribed threads.
        // Falls back to any recent SEV>=9 event if user has no thread subscriptions.
        $threadIds = $subscriber->threads()->pluck('conflict_threads.id')->toArray();

        $event = Event::query()
            ->where('severity', '>=', 9)
            ->whereIn('status', ['corroborated', 'confirmed'])
            ->whereNotNull('conflict_thread_id')
            ->when(! empty($threadIds), fn ($q) => $q->whereIn('conflict_thread_id', $threadIds))
            ->orderByDesc('occurred_at')
            ->first();

        if (! $event) {
            return response('No SEV≥9 event found to preview (try subscribing to a thread first)', 404);
        }

        $thread = ConflictThread::find($event->conflict_thread_id);
        $mail = new CriticalAlertMail($subscriber, $event, $thread);

        return response($mail->render(), 200, ['Content-Type' => 'text/html']);
    }

    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $email = $subscriber->email;
        $subscriber->delete();

        return redirect()->route('admin.subscribers.index')
            ->with('success', 'Subscriber '.$email.' deleted (GDPR).');
    }
}
