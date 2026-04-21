<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('app.admin_secret');

        // Check Authorization: Bearer header first
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            if ($token === $secret) {
                $request->session()->put('admin_authenticated', true);
                return $next($request);
            }

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return redirect()->route('admin.login');
        }

        // Check encrypted session cookie
        if ($request->session()->get('admin_authenticated') === true) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return redirect()->route('admin.login');
    }
}
