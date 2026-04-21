<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialChannel;
use App\Models\SocialPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSocialChannelController extends Controller
{
    public function index(): Response
    {
        $channels = SocialChannel::query()
            ->orderByDesc('enabled')
            ->orderBy('platform')
            ->orderBy('locale')
            ->get()
            ->map(function (SocialChannel $channel) {
                // Strip credentials from the response, only pass non-sensitive info
                $creds = $channel->credentials ?? [];

                return [
                    'id' => $channel->id,
                    'platform' => $channel->platform,
                    'locale' => $channel->locale,
                    'name' => $channel->name,
                    'handle' => $channel->handle,
                    'posts_event' => $channel->posts_event,
                    'posts_briefing' => $channel->posts_briefing,
                    'enabled' => $channel->enabled,
                    'unlimited_chars' => $channel->unlimited_chars,
                    'daily_post_count' => $channel->daily_post_count,
                    'daily_post_limit' => $channel->daily_post_limit,
                    'min_post_interval' => $channel->min_post_interval,
                    'last_posted_at' => $channel->last_posted_at,
                    'token_expires_at' => $channel->token_expires_at,
                    'created_at' => $channel->created_at,
                    'has_credentials' => ! empty($creds),
                    // Pass credential keys (not values) so the UI can show which fields are set
                    'credential_keys' => array_keys($creds),
                ];
            });

        $recentPosts = SocialPost::query()
            ->with('socialChannel:id,name,platform,locale')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'social_channel_id', 'postable_type', 'postable_id', 'platform', 'locale', 'status', 'error', 'published_at', 'created_at']);

        return Inertia::render('Admin/SocialChannels', [
            'channels' => $channels,
            'recentPosts' => $recentPosts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'in:threads,facebook,telegram,bluesky,x'],
            'locale' => ['required', 'string', 'max:5'],
            'name' => ['required', 'string', 'max:100'],
            'handle' => ['required', 'string', 'max:100'],
            'posts_event' => ['boolean'],
            'posts_briefing' => ['boolean'],
            'enabled' => ['boolean'],
            'unlimited_chars' => ['boolean'],
            'daily_post_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'min_post_interval' => ['required', 'integer', 'min:0', 'max:86400'],
            'credentials' => ['required', 'array'],
        ]);

        $validated['posts_event'] = $request->boolean('posts_event', true);
        $validated['posts_briefing'] = $request->boolean('posts_briefing', true);
        $validated['enabled'] = $request->boolean('enabled', false);
        $validated['unlimited_chars'] = $request->boolean('unlimited_chars', false);

        SocialChannel::create($validated);

        return redirect()->route('admin.social-channels.index')
            ->with('success', 'Social channel created.');
    }

    public function update(Request $request, SocialChannel $socialChannel): RedirectResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'in:threads,facebook,telegram,bluesky,x'],
            'locale' => ['required', 'string', 'max:5'],
            'name' => ['required', 'string', 'max:100'],
            'handle' => ['required', 'string', 'max:100'],
            'posts_event' => ['boolean'],
            'posts_briefing' => ['boolean'],
            'enabled' => ['boolean'],
            'unlimited_chars' => ['boolean'],
            'daily_post_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'min_post_interval' => ['required', 'integer', 'min:0', 'max:86400'],
            'credentials' => ['nullable', 'array'],
        ]);

        $validated['posts_event'] = $request->boolean('posts_event', $socialChannel->posts_event);
        $validated['posts_briefing'] = $request->boolean('posts_briefing', $socialChannel->posts_briefing);
        $validated['enabled'] = $request->boolean('enabled', $socialChannel->enabled);
        $validated['unlimited_chars'] = $request->boolean('unlimited_chars', $socialChannel->unlimited_chars);

        // Only update credentials if provided (allows editing other fields without re-entering tokens)
        if (empty($validated['credentials'])) {
            unset($validated['credentials']);
        }

        $socialChannel->update($validated);

        return redirect()->route('admin.social-channels.index')
            ->with('success', 'Social channel updated.');
    }

    public function destroy(SocialChannel $socialChannel): RedirectResponse
    {
        $socialChannel->delete();

        return redirect()->route('admin.social-channels.index')
            ->with('success', 'Social channel deleted.');
    }
}
