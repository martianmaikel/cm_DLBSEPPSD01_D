<?php

namespace App\Services\Social\Drivers;

use App\Contracts\SocialDriver;
use App\DataTransferObjects\SocialPublishResult;
use App\Models\SocialChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThreadsDriver implements SocialDriver
{
    private const API_BASE = 'https://graph.threads.net/v1.0';

    public function publish(SocialChannel $channel, string $text, ?string $link = null, ?array $media = null): SocialPublishResult
    {
        $credentials = $channel->credentials;
        $userId = $credentials['user_id'] ?? null;
        $accessToken = $credentials['access_token'] ?? null;

        if (! $userId || ! $accessToken) {
            return new SocialPublishResult(false, error: 'Missing user_id or access_token in channel credentials');
        }

        try {
            // Step 1: Create media container
            $containerPayload = [
                'media_type' => 'TEXT',
                'text' => $text,
                'access_token' => $accessToken,
            ];

            if ($link) {
                $containerPayload['link_attachment'] = $link;
            }

            $containerResponse = Http::timeout(15)
                ->post(self::API_BASE . "/{$userId}/threads", $containerPayload);

            if (! $containerResponse->successful() || ! $containerResponse->json('id')) {
                $error = $containerResponse->json('error.message') ?? $containerResponse->body();
                $errorCode = $containerResponse->json('error.code');

                if ($errorCode === 190) {
                    return new SocialPublishResult(false, error: "TOKEN_EXPIRED: {$error}");
                }

                Log::warning('ThreadsDriver: container creation failed', [
                    'channel_id' => $channel->id,
                    'error' => $error,
                ]);
                return new SocialPublishResult(false, error: $error);
            }

            $creationId = $containerResponse->json('id');

            // Step 2: Publish the container
            // Threads recommends waiting before publishing; use a short sleep
            usleep(2_000_000); // 2 seconds

            $publishResponse = Http::timeout(15)
                ->post(self::API_BASE . "/{$userId}/threads_publish", [
                    'creation_id' => $creationId,
                    'access_token' => $accessToken,
                ]);

            if ($publishResponse->successful() && $publishResponse->json('id')) {
                return new SocialPublishResult(true, platformPostId: $publishResponse->json('id'));
            }

            $error = $publishResponse->json('error.message') ?? $publishResponse->body();
            Log::warning('ThreadsDriver: publish failed', [
                'channel_id' => $channel->id,
                'creation_id' => $creationId,
                'error' => $error,
            ]);

            return new SocialPublishResult(false, error: $error);
        } catch (\Throwable $e) {
            Log::error('ThreadsDriver: request failed', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);

            return new SocialPublishResult(false, error: $e->getMessage());
        }
    }

    public function refreshToken(SocialChannel $channel): bool
    {
        $credentials = $channel->credentials;
        $accessToken = $credentials['access_token'] ?? null;

        if (! $accessToken) {
            return false;
        }

        try {
            $response = Http::timeout(15)
                ->get(self::API_BASE . '/refresh_access_token', [
                    'grant_type' => 'th_refresh_token',
                    'access_token' => $accessToken,
                ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                Log::error('ThreadsDriver: token refresh failed', [
                    'channel_id' => $channel->id,
                    'error' => $response->body(),
                ]);
                return false;
            }

            $credentials['access_token'] = $response->json('access_token');
            $expiresIn = $response->json('expires_in', 5184000); // default 60 days

            $channel->update([
                'credentials' => $credentials,
                'token_expires_at' => now()->addSeconds($expiresIn),
            ]);

            Log::info('ThreadsDriver: token refreshed', [
                'channel_id' => $channel->id,
                'expires_at' => $channel->token_expires_at,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('ThreadsDriver: token refresh exception', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
