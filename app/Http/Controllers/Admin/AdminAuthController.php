<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Admin/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        if ($validated['token'] !== config('app.admin_secret')) {
            return back()->withErrors(['token' => 'Invalid token.']);
        }

        $request->session()->put('admin_authenticated', true);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_authenticated');
        $request->session()->regenerate();

        return redirect()->route('admin.login');
    }
}
