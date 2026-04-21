<?php

namespace App\Services\Social\Drivers;

use App\Contracts\SocialDriver;
use App\DataTransferObjects\SocialPublishResult;
use App\Models\SocialChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramDriver implements SocialDriver
{
    public function publish(SocialChannel $channel, string $text, ?string $link = null, ?array $media = null): SocialPublishResult
    {
        $credentials = $channel->credentials;
        $botToken = $credentials['bot_token'] ?? config('services.telegram.bot_token');
        $chatId = $credentials['chat_id'] ?? null;

        if (! $botToken || ! $chatId) {
            return new SocialPublishResult(false, error: 'Missing bot_token or chat_id in channel credentials');
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => config('social.telegram.parse_mode', 'HTML'),
        ];

        // Disable link preview if a link is embedded in the text (cleaner look)
        if ($link) {
            $payload['link_preview_options'] = ['is_disabled' => false, 'url' => $link];
        }

        try {
            $response = Http::timeout(15)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);

            if ($response->successful() && $response->json('ok')) {
                $messageId = $response->json('result.message_id');

                return new SocialPublishResult(true, platformPostId: (string) $messageId);
            }

            $error = $response->json('description') ?? $response->body();
            Log::warning('TelegramDriver: API error', [
                'channel_id' => $channel->id,
                'status' => $response->status(),
                'error' => $error,
            ]);

            return new SocialPublishResult(false, error: $error);
        } catch (\Throwable $e) {
            Log::error('TelegramDriver: request failed', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);

            return new SocialPublishResult(false, error: $e->getMessage());
        }
    }

    public function refreshToken(SocialChannel $channel): bool
    {
        // Telegram bot tokens don't expire
        return true;
    }
}
