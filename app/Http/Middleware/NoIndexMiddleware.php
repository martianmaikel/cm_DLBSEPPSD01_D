<?php

namespace App\Http\Middleware;

use App\DataTransferObjects\SeoMeta;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoIndexMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('seo', SeoMeta::make(
            robots: 'noindex,nofollow',
        ));

        return $next($request);
    }
}
