<?php

namespace App\Services\Social\Drivers;

use App\Contracts\SocialDriver;
use App\DataTransferObjects\SocialPublishResult;
use App\Models\SocialChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookDriver implements SocialDriver
{
    private const API_VERSION = 'v25.0';

    public function publish(SocialChannel $channel, string $text, ?string $link = null, ?array $media = null): SocialPublishResult
    {
        $credentials = $channel->credentials;
        $pageId = $credentials['page_id'] ?? null;
        $accessToken = $credentials['access_token'] ?? null;

        if (! $pageId || ! $accessToken) {
            return new SocialPublishResult(false, error: 'Missing page_id or access_token in channel credentials');
        }

        $payload = [
            'message' => $text,
            'access_token' => $accessToken,
        ];

        if ($link) {
            $payload['link'] = $link;
        }

        try {
            $url = 'https://graph.facebook.com/' . self::API_VERSION . "/{$pageId}/feed";
            $response = Http::timeout(15)->post($url, $payload);

            if ($response->successful() && $response->json('id')) {
                return new SocialPublishResult(true, platformPostId: $response->json('id'));
            }

            $error = $response->json('error.message') ?? $response->body();
            $errorCode = $response->json('error.code');

            Log::warning('FacebookDriver: API error', [
                'channel_id' => $channel->id,
                'status' => $response->status(),
                'error_code' => $errorCode,
                'error' => $error,
            ]);

            // Check for token expiry (code 190)
            if ($errorCode === 190) {
                return new SocialPublishResult(false, error: "TOKEN_EXPIRED: {$error}");
            }

            return new SocialPublishResult(false, error: $error);
        } catch (\Throwable $e) {
            Log::error('FacebookDriver: request failed', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);

            return new SocialPublishResult(false, error: $e->getMessage());
        }
    }

    public function refreshToken(SocialChannel $channel): bool
    {
        $credentials = $channel->credentials;
        $userToken = $credentials['long_lived_user_token'] ?? null;

        if (! $userToken) {
            Log::warning('FacebookDriver: no long_lived_user_token for refresh', ['channel_id' => $channel->id]);
            return false;
        }

        try {
            // Exchange long-lived user token for a fresh page token
            $url = 'https://graph.facebook.com/' . self::API_VERSION . '/me/accounts';
            $response = Http::timeout(15)->get($url, [
                'access_token' => $userToken,
            ]);

            if (! $response->successful()) {
                Log::error('FacebookDriver: token refresh failed', [
                    'channel_id' => $channel->id,
                    'error' => $response->body(),
                ]);
                return false;
            }

            $pageId = $credentials['page_id'];
            $pages = collect($response->json('data', []));
            $page = $pages->firstWhere('id', $pageId);

            if (! $page || ! isset($page['access_token'])) {
                Log::error('FacebookDriver: page not found in token refresh response', [
                    'channel_id' => $channel->id,
                    'page_id' => $pageId,
                ]);
                return false;
            }

            $credentials['access_token'] = $page['access_token'];
            $channel->update([
                'credentials' => $credentials,
                // Page tokens obtained from long-lived user tokens don't expire
                'token_expires_at' => null,
            ]);

            Log::info('FacebookDriver: token refreshed', ['channel_id' => $channel->id]);
            return true;
        } catch (\Throwable $e) {
            Log::error('FacebookDriver: token refresh exception', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
