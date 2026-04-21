<?php

namespace App\Http\Controllers;

use App\Models\NewsletterAffiliate;
use App\Models\NewsletterAffiliateClick;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AffiliateRedirectController extends Controller
{
    public function redirect(Request $request, string $slug): RedirectResponse|Response
    {
        $affiliate = NewsletterAffiliate::where('slug', $slug)->first();

        if (! $affiliate) {
            return response('Unknown affiliate', 404);
        }

        // Resolve subscriber from short id prefix (first 8 chars of UUID)
        $subscriberId = null;
        if ($shortId = $request->query('s')) {
            $match = NewsletterSubscriber::query()
                ->where('id', 'LIKE', $shortId.'%')
                ->value('id');
            $subscriberId = $match;
        }

        NewsletterAffiliateClick::create([
            'affiliate_id' => $affiliate->id,
            'subscriber_id' => $subscriberId,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'clicked_at' => now(),
        ]);

        $affiliate->increment('click_count');

        return redirect()->away($this->appendUtm($affiliate));
    }

    private function appendUtm(NewsletterAffiliate $a): string
    {
        $params = array_filter([
            'utm_source' => $a->utm_source,
            'utm_medium' => $a->utm_medium,
            'utm_campaign' => $a->utm_campaign ?: $a->slug,
        ]);

        if (empty($params)) {
            return $a->target_url;
        }

        $separator = str_contains($a->target_url, '?') ? '&' : '?';
        return $a->target_url.$separator.http_build_query($params);
    }
}
