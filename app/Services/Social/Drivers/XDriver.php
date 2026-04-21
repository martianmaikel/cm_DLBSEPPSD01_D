<?php

namespace App\Services\Social\Drivers;

use App\Contracts\SocialDriver;
use App\DataTransferObjects\SocialPublishResult;
use App\Models\SocialChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class XDriver implements SocialDriver
{
    private const TWEETS_URL = 'https://api.twitter.com/2/tweets';

    private const MEDIA_UPLOAD_URL = 'https://upload.twitter.com/1.1/media/upload.json';

    private const MAX_MEDIA = 4;

    private const MIN_IMAGE_WIDTH = 400;

    private const MIN_IMAGE_HEIGHT = 300;

    private const MIN_IMAGE_BYTES = 15_000; // 15KB — anything smaller is a thumbnail/preview

    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024; // 5MB

    private const MAX_VIDEO_BYTES = 50 * 1024 * 1024; // 50MB practical limit

    private const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB chunks for video upload

    private const VIDEO_PROCESSING_TIMEOUT = 120; // seconds to wait for transcoding

    public function publish(SocialChannel $channel, string $text, ?string $link = null, ?array $media = null): SocialPublishResult
    {
        $credentials = $channel->credentials;
        $apiKey = $credentials['api_key'] ?? null;
        $apiSecret = $credentials['api_secret'] ?? null;
        $accessToken = $credentials['access_token'] ?? null;
        $accessTokenSecret = $credentials['access_token_secret'] ?? null;

        if (! $apiKey || ! $apiSecret || ! $accessToken || ! $accessTokenSecret) {
            return new SocialPublishResult(false, error: 'Missing OAuth credentials (api_key, api_secret, access_token, access_token_secret)');
        }

        $oauthKeys = [$apiKey, $apiSecret, $accessToken, $accessTokenSecret];

        // X posts are text-only (no link) for better reach — links trigger
        // spam heuristics and algorithmic suppression on new accounts
        $postText = $text;

        try {
            $mediaIds = [];
            if (! empty($media)) {
                $mediaIds = $this->uploadMedia($media, $oauthKeys);
            }

            $tweetPayload = ['text' => $postText];
            if (! empty($mediaIds)) {
                $tweetPayload['media'] = ['media_ids' => $mediaIds];
            }

            $authHeader = $this->buildOAuthHeader('POST', self::TWEETS_URL, ...$oauthKeys);

            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::TWEETS_URL, $tweetPayload);

            if ($response->successful() && $response->json('data.id')) {
                return new SocialPublishResult(true, platformPostId: $response->json('data.id'));
            }

            $error = $response->json('detail') ?? $response->json('title') ?? $response->body();
            $errorCode = $response->status();

            Log::warning('XDriver: API error', [
                'channel_id' => $channel->id,
                'status' => $errorCode,
                'error' => $error,
            ]);

            if ($errorCode === 401) {
                return new SocialPublishResult(false, error: "TOKEN_EXPIRED: {$error}");
            }

            return new SocialPublishResult(false, error: $error);
        } catch (\Throwable $e) {
            Log::error('XDriver: request failed', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);

            return new SocialPublishResult(false, error: $e->getMessage());
        }
    }

    public function refreshToken(SocialChannel $channel): bool
    {
        return true;
    }

    // ── Media upload ──

    /**
     * Download and upload media to X. Returns an array of media_id strings.
     * Handles both images (simple upload) and videos (chunked upload).
     * Prefers video over images when both are available.
     */
    private function uploadMedia(array $media, array $oauthKeys): array
    {
        // Separate by type
        $videos = array_filter($media, fn ($m) => ($m['type'] ?? '') === 'video' && ! empty($m['url']));
        $images = array_filter($media, fn ($m) => ($m['type'] ?? '') === 'image' && ! empty($m['url']));

        // If we have a video, prefer it (X shows video more prominently than images)
        // Only use the first video — X allows max 1 video per tweet
        if (! empty($videos)) {
            $video = array_values($videos)[0];
            $mediaId = $this->uploadVideo($video, $oauthKeys);
            if ($mediaId) {
                return [$mediaId];
            }
            // Video upload failed — fall through to images
        }

        // Upload images (max 4)
        $images = array_slice(array_values($images), 0, self::MAX_MEDIA);

        $mediaIds = [];
        foreach ($images as $item) {
            $mediaId = $this->uploadImage($item, $oauthKeys);
            if ($mediaId) {
                $mediaIds[] = $mediaId;
            }
        }

        return $mediaIds;
    }

    /**
     * Simple upload for images (max 5MB).
     */
    private function uploadImage(array $item, array $oauthKeys): ?string
    {
        try {
            $imageResponse = Http::timeout(10)->get($item['url']);
            if ($imageResponse->failed()) {
                Log::debug('XDriver: image download failed', ['url' => $item['url'], 'status' => $imageResponse->status()]);

                return null;
            }

            $imageData = $imageResponse->body();
            $imageSize = strlen($imageData);

            // Skip tiny images (likely video thumbnails or previews)
            if ($imageSize < self::MIN_IMAGE_BYTES) {
                Log::debug('XDriver: image too small, skipping thumbnail', ['url' => $item['url'], 'size' => $imageSize]);

                return null;
            }

            // Check dimensions — reject low-resolution previews
            $dimensions = @getimagesizefromstring($imageData);
            if ($dimensions !== false) {
                [$width, $height] = $dimensions;
                if ($width < self::MIN_IMAGE_WIDTH || $height < self::MIN_IMAGE_HEIGHT) {
                    Log::debug('XDriver: image resolution too low, skipping', ['url' => $item['url'], 'width' => $width, 'height' => $height]);

                    return null;
                }
            }

            if ($imageSize > self::MAX_IMAGE_BYTES) {
                Log::debug('XDriver: image too large, skipping', ['url' => $item['url'], 'size' => $imageSize]);

                return null;
            }

            $authHeader = $this->buildOAuthHeader('POST', self::MEDIA_UPLOAD_URL, ...$oauthKeys);

            $uploadResponse = Http::timeout(30)
                ->withHeaders(['Authorization' => $authHeader])
                ->attach('media_data', base64_encode($imageData), 'image')
                ->post(self::MEDIA_UPLOAD_URL);

            if ($uploadResponse->successful() && $uploadResponse->json('media_id_string')) {
                Log::debug('XDriver: image uploaded', [
                    'media_id' => $uploadResponse->json('media_id_string'),
                    'url' => $item['url'],
                    'size' => $imageSize,
                ]);

                return $uploadResponse->json('media_id_string');
            }

            Log::debug('XDriver: image upload failed', ['url' => $item['url'], 'status' => $uploadResponse->status(), 'error' => $uploadResponse->body()]);
        } catch (\Throwable $e) {
            Log::debug('XDriver: image upload error', ['url' => $item['url'], 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Chunked upload for videos (INIT → APPEND → FINALIZE → poll STATUS).
     * X transcodes the video async after FINALIZE — we poll until ready or timeout.
     */
    private function uploadVideo(array $item, array $oauthKeys): ?string
    {
        try {
            // Download video in-memory
            $videoResponse = Http::timeout(60)->get($item['url']);
            if ($videoResponse->failed()) {
                Log::debug('XDriver: video download failed', ['url' => $item['url'], 'status' => $videoResponse->status()]);

                return null;
            }

            $videoData = $videoResponse->body();
            $totalBytes = strlen($videoData);

            if ($totalBytes > self::MAX_VIDEO_BYTES) {
                Log::debug('XDriver: video too large, skipping', ['url' => $item['url'], 'size' => $totalBytes]);

                return null;
            }

            $contentType = $videoResponse->header('Content-Type') ?: 'video/mp4';

            // Step 1: INIT — form params must be included in OAuth signature
            $initParams = [
                'command' => 'INIT',
                'total_bytes' => $totalBytes,
                'media_type' => $contentType,
                'media_category' => 'tweet_video',
            ];
            $authHeader = $this->buildOAuthHeader('POST', self::MEDIA_UPLOAD_URL, ...$oauthKeys, formParams: $initParams);
            $initResponse = Http::timeout(15)
                ->withHeaders(['Authorization' => $authHeader])
                ->asForm()
                ->post(self::MEDIA_UPLOAD_URL, $initParams);

            if (! $initResponse->successful() || ! $initResponse->json('media_id_string')) {
                Log::debug('XDriver: video INIT failed', ['url' => $item['url'], 'status' => $initResponse->status(), 'error' => $initResponse->body()]);

                return null;
            }

            $mediaId = $initResponse->json('media_id_string');

            // Step 2: APPEND (chunked)
            $chunks = str_split($videoData, self::CHUNK_SIZE);
            unset($videoData); // free memory

            foreach ($chunks as $index => $chunk) {
                // APPEND uses multipart — command params go in the URL query string,
                // media_data goes as multipart body. Only query params are in the OAuth signature.
                $appendQuery = http_build_query([
                    'command' => 'APPEND',
                    'media_id' => $mediaId,
                    'segment_index' => $index,
                ]);
                $appendUrl = self::MEDIA_UPLOAD_URL . '?' . $appendQuery;
                $authHeader = $this->buildOAuthHeader('POST', $appendUrl, ...$oauthKeys);

                $appendResponse = Http::timeout(60)
                    ->withHeaders(['Authorization' => $authHeader])
                    ->attach('media_data', $chunk, 'chunk')
                    ->post($appendUrl);

                // APPEND returns 2xx with empty body on success
                if (! $appendResponse->successful()) {
                    Log::debug('XDriver: video APPEND failed', ['media_id' => $mediaId, 'segment' => $index, 'status' => $appendResponse->status()]);

                    return null;
                }
            }

            // Step 3: FINALIZE — form params must be in OAuth signature
            $finalizeParams = [
                'command' => 'FINALIZE',
                'media_id' => $mediaId,
            ];
            $authHeader = $this->buildOAuthHeader('POST', self::MEDIA_UPLOAD_URL, ...$oauthKeys, formParams: $finalizeParams);
            $finalizeResponse = Http::timeout(15)
                ->withHeaders(['Authorization' => $authHeader])
                ->asForm()
                ->post(self::MEDIA_UPLOAD_URL, $finalizeParams);

            if (! $finalizeResponse->successful()) {
                Log::debug('XDriver: video FINALIZE failed', ['media_id' => $mediaId, 'status' => $finalizeResponse->status(), 'error' => $finalizeResponse->body()]);

                return null;
            }

            // Step 4: Poll STATUS until processing completes
            $processingInfo = $finalizeResponse->json('processing_info');
            if ($processingInfo) {
                $mediaId = $this->waitForProcessing($mediaId, $processingInfo, $oauthKeys);
                if (! $mediaId) {
                    return null;
                }
            }

            Log::info('XDriver: video uploaded', ['media_id' => $mediaId, 'url' => $item['url'], 'size' => $totalBytes]);

            return $mediaId;
        } catch (\Throwable $e) {
            Log::debug('XDriver: video upload error', ['url' => $item['url'], 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Poll the media STATUS endpoint until video processing completes.
     */
    private function waitForProcessing(string $mediaId, array $processingInfo, array $oauthKeys): ?string
    {
        $elapsed = 0;

        while ($elapsed < self::VIDEO_PROCESSING_TIMEOUT) {
            $state = $processingInfo['state'] ?? '';

            if ($state === 'succeeded') {
                return $mediaId;
            }

            if ($state === 'failed') {
                $error = $processingInfo['error']['message'] ?? 'unknown';
                Log::debug('XDriver: video processing failed', ['media_id' => $mediaId, 'error' => $error]);

                return null;
            }

            // Wait the recommended time before checking again
            $waitSeconds = $processingInfo['check_after_secs'] ?? 5;
            $waitSeconds = min($waitSeconds, 15); // cap at 15s per poll
            sleep($waitSeconds);
            $elapsed += $waitSeconds;

            // Check status
            $statusUrl = self::MEDIA_UPLOAD_URL . '?' . http_build_query([
                'command' => 'STATUS',
                'media_id' => $mediaId,
            ]);
            $authHeader = $this->buildOAuthHeader('GET', $statusUrl, ...$oauthKeys);

            $statusResponse = Http::timeout(10)
                ->withHeaders(['Authorization' => $authHeader])
                ->get(self::MEDIA_UPLOAD_URL, [
                    'command' => 'STATUS',
                    'media_id' => $mediaId,
                ]);

            if (! $statusResponse->successful()) {
                Log::debug('XDriver: video STATUS check failed', ['media_id' => $mediaId, 'status' => $statusResponse->status()]);

                return null;
            }

            $processingInfo = $statusResponse->json('processing_info', []);
        }

        Log::debug('XDriver: video processing timed out', ['media_id' => $mediaId, 'elapsed' => $elapsed]);

        return null;
    }

    // ── OAuth ──

    /**
     * Build an OAuth 1.0a Authorization header with HMAC-SHA1 signature.
     */
    /**
     * Build an OAuth 1.0a Authorization header with HMAC-SHA1 signature.
     *
     * @param  array  $formParams  For application/x-www-form-urlencoded POST requests,
     *                              the body parameters MUST be included in the signature.
     *                              For multipart/form-data (file uploads), pass empty array.
     */
    private function buildOAuthHeader(
        string $method,
        string $url,
        string $consumerKey,
        string $consumerSecret,
        string $token,
        string $tokenSecret,
        array $formParams = [],
    ): string {
        // Strip query parameters from URL for signature base string
        $baseUrl = strtok($url, '?');

        $oauthParams = [
            'oauth_consumer_key' => $consumerKey,
            'oauth_nonce' => Str::random(32),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $token,
            'oauth_version' => '1.0',
        ];

        // Collect all parameters for signature: OAuth + query string + form body
        $allParams = $oauthParams;

        $query = parse_url($url, PHP_URL_QUERY);
        if ($query) {
            parse_str($query, $queryParams);
            $allParams = array_merge($allParams, $queryParams);
        }

        // Form-encoded body params must be included in the signature
        // (but NOT multipart body params — those are excluded per OAuth spec)
        foreach ($formParams as $k => $v) {
            $allParams[$k] = (string) $v;
        }

        $paramString = collect($allParams)
            ->sortKeys()
            ->map(fn ($v, $k) => rawurlencode($k) . '=' . rawurlencode($v))
            ->implode('&');

        $baseString = strtoupper($method) . '&' . rawurlencode($baseUrl) . '&' . rawurlencode($paramString);

        $signingKey = rawurlencode($consumerSecret) . '&' . rawurlencode($tokenSecret);
        $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

        $oauthParams['oauth_signature'] = $signature;

        $headerParts = collect($oauthParams)
            ->sortKeys()
            ->map(fn ($v, $k) => rawurlencode($k) . '="' . rawurlencode($v) . '"')
            ->implode(', ');

        return 'OAuth ' . $headerParts;
    }
}
