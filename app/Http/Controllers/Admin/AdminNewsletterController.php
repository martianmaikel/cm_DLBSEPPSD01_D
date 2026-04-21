<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterAffiliate;
use App\Models\NewsletterSend;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminNewsletterController extends Controller
{
    public function stats(): Response
    {
        // Subscriber status distribution
        $subscribersByStatus = NewsletterSubscriber::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Sends per day (last 14 days) grouped by type
        $sendsByDay = NewsletterSend::query()
            ->selectRaw("date_trunc('day', sent_at)::date as day, type, count(*) as count")
            ->where('sent_at', '>=', now()->subDays(14))
            ->whereNotNull('sent_at')
            ->groupBy('day', 'type')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day' => (string) $r->day,
                'type' => $r->type,
                'count' => (int) $r->count,
            ])
            ->toArray();

        // Status distribution for all sends
        $sendsByStatus = NewsletterSend::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Total sends by type
        $sendsByType = NewsletterSend::query()
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        // Top affiliates by impressions + CTR
        $topAffiliates = NewsletterAffiliate::query()
            ->select(['id', 'name', 'slug', 'active', 'impression_count', 'click_count'])
            ->where('impression_count', '>', 0)
            ->orderByDesc('impression_count')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'slug' => $a->slug,
                'active' => $a->active,
                'impressions' => $a->impression_count,
                'clicks' => $a->click_count,
                'ctr' => $a->impression_count > 0
                    ? round(($a->click_count / $a->impression_count) * 100, 2)
                    : 0,
            ])
            ->toArray();

        // Bounce rate (last 30 days)
        $totalRecent = NewsletterSend::where('sent_at', '>=', now()->subDays(30))->whereNotNull('sent_at')->count();
        $bouncedRecent = NewsletterSend::where('sent_at', '>=', now()->subDays(30))
            ->whereIn('status', ['bounced', 'failed'])
            ->count();
        $bounceRate = $totalRecent > 0 ? round(($bouncedRecent / $totalRecent) * 100, 2) : 0;

        // Recent SES events (last 50)
        $recentSesEvents = DB::table('newsletter_ses_events')
            ->select(['id', 'event_type', 'recipient_email', 'received_at'])
            ->orderByDesc('received_at')
            ->limit(20)
            ->get()
            ->toArray();

        return Inertia::render('Admin/NewsletterStats', [
            'subscribersByStatus' => $subscribersByStatus,
            'sendsByDay' => $sendsByDay,
            'sendsByStatus' => $sendsByStatus,
            'sendsByType' => $sendsByType,
            'topAffiliates' => $topAffiliates,
            'bounceRate' => $bounceRate,
            'totalSubscribers' => array_sum($subscribersByStatus),
            'recentSesEvents' => $recentSesEvents,
        ]);
    }
}
