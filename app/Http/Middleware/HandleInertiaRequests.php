<?php

namespace App\Http\Middleware;

use App\DataTransferObjects\SeoMeta;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function handle(Request $request, Closure $next)
    {
        // Server-side locale detection for SEO crawlers
        $locale = $request->query('lang', $request->cookie('fw-lang', 'en'));
        if (in_array($locale, ['en', 'de'])) {
            app()->setLocale($locale);
        }

        return parent::handle($request, $next);
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'locale' => app()->getLocale(),
            'appName' => config('app.name'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'seo' => fn () => $request->attributes->get('seo', SeoMeta::make(
                description: __('seo.home.description'),
                ogImage: url('/images/og-banner.jpg'),
                twitterCard: 'summary_large_image',
            ))->toArray(),
        ];
    }
}
