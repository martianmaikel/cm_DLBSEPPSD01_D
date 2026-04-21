<?php

namespace App\Services\Social\Drivers;

use App\Contracts\SocialDriver;
use App\DataTransferObjects\SocialPublishResult;
use App\Models\SocialChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlueskyDriver implements SocialDriver
{
    private const API_BASE = 'https://bsky.social/xrpc';

    private const MAX_IMAGES = 4;

    private const MAX_BLOB_BYTES = 1_000_000; // Bluesky's 1MB blob limit for images

    private const MIN_IMAGE_WIDTH = 400;

    private const MIN_IMAGE_HEIGHT = 300;

    private const MIN_IMAGE_BYTES = 15_000;

    public function publish(SocialChannel $channel, string $text, ?string $link = null, ?array $media = null): SocialPublishResult
    {
        $credentials = $channel->credentials;
        $identifier = $credentials['identifier'] ?? null;
        $password = $credentials['app_password'] ?? null;

        if (! $identifier || ! $password) {
            return new SocialPublishResult(false, error: 'Missing identifier or app_password in channel credentials');
        }

        try {
            $session = $this->createSession($identifier, $password);
            if (! $session) {
                return new SocialPublishResult(false, error: 'Failed to create Bluesky session');
            }

            $accessJwt = $session['accessJwt'];
            $did = $session['did'];

            $record = [
                '$type' => 'app.bsky.feed.post',
                'text' => $text,
                'createdAt' => now()->toIso8601ZuluString(),
            ];

            $facets = $this->detectUrlFacets($text);
            if (! empty($facets)) {
                $record['facets'] = $facets;
            }

            // Posts mirror X: text + hashtags only, no event link card.
            // Image embeds are still attached when media is available.
            $imageBlobs = $this->uploadImages($media, $accessJwt);

            if (! empty($imageBlobs)) {
                $record['embed'] = [
                    '$type' => 'app.bsky.embed.images',
                    'images' => $imageBlobs,
                ];
            }

            $response = Http::timeout(15)
                ->withToken($accessJwt)
                ->post(self::API_BASE . '/com.atproto.repo.createRecord', [
                    'repo' => $did,
                    'collection' => 'app.bsky.feed.post',
                    'record' => $record,
                ]);

            if ($response->successful() && $response->json('uri')) {
                return new SocialPublishResult(true, platformPostId: $response->json('uri'));
            }

            $error = $response->json('message') ?? $response->body();
            Log::warning('BlueskyDriver: createRecord failed', [
                'channel_id' => $channel->id,
                'error' => $error,
            ]);

            return new SocialPublishResult(false, error: $error);
        } catch (\Throwable $e) {
            Log::error('BlueskyDriver: request failed', [
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
     * Download, validate, and upload images as blobs to Bluesky.
     * Returns an array of image embed entries ready for app.bsky.embed.images.
     */
    private function uploadImages(?array $media, string $accessJwt): array
    {
        if (empty($media)) {
            return [];
        }

        $images = array_filter($media, fn ($m) => ($m['type'] ?? '') === 'image' && ! empty($m['url']));
        $images = array_slice(array_values($images), 0, self::MAX_IMAGES);

        $blobs = [];

        foreach ($images as $item) {
            $blob = $this->uploadSingleImage($item, $accessJwt);
            if ($blob) {
                $blobs[] = $blob;
            }
        }

        return $blobs;
    }

    private function uploadSingleImage(array $item, string $accessJwt): ?array
    {
        try {
            $imageResponse = Http::timeout(10)->get($item['url']);
            if ($imageResponse->failed()) {
                Log::debug('BlueskyDriver: image download failed', ['url' => $item['url'], 'status' => $imageResponse->status()]);

                return null;
            }

            $imageData = $imageResponse->body();
            $imageSize = strlen($imageData);

            // Skip tiny images (thumbnails/previews)
            if ($imageSize < self::MIN_IMAGE_BYTES) {
                Log::debug('BlueskyDriver: image too small, skipping', ['url' => $item['url'], 'size' => $imageSize]);

                return null;
            }

            // Check dimensions
            $dimensions = @getimagesizefromstring($imageData);
            if ($dimensions !== false) {
                [$width, $height] = $dimensions;
                if ($width < self::MIN_IMAGE_WIDTH || $height < self::MIN_IMAGE_HEIGHT) {
                    Log::debug('BlueskyDriver: image resolution too low', ['url' => $item['url'], 'width' => $width, 'height' => $height]);

                    return null;
                }
            }

            // Bluesky blob limit is 1MB — resize if needed
            $contentType = $imageResponse->header('Content-Type') ?: 'image/jpeg';
            if ($imageSize > self::MAX_BLOB_BYTES) {
                $imageData = $this->compressImage($imageData, $contentType);
                if ($imageData === null || strlen($imageData) > self::MAX_BLOB_BYTES) {
                    Log::debug('BlueskyDriver: image still too large after compression', ['url' => $item['url'], 'size' => strlen($imageData ?? '')]);

                    return null;
                }
                $imageSize = strlen($imageData);
                $contentType = 'image/jpeg'; // compression always outputs JPEG
            }

            // Upload blob
            $uploadResponse = Http::timeout(30)
                ->withToken($accessJwt)
                ->withHeaders(['Content-Type' => $contentType])
                ->withBody($imageData, $contentType)
                ->post(self::API_BASE . '/com.atproto.repo.uploadBlob');

            if (! $uploadResponse->successful() || ! $uploadResponse->json('blob')) {
                Log::debug('BlueskyDriver: blob upload failed', ['url' => $item['url'], 'status' => $uploadResponse->status(), 'error' => $uploadResponse->body()]);

                return null;
            }

            Log::debug('BlueskyDriver: image uploaded', ['url' => $item['url'], 'size' => $imageSize]);

            return [
                'alt' => '', // alt text — could be populated from event title in the future
                'image' => $uploadResponse->json('blob'),
            ];
        } catch (\Throwable $e) {
            Log::debug('BlueskyDriver: image upload error', ['url' => $item['url'], 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Compress an image to fit within Bluesky's 1MB blob limit.
     * Uses GD to re-encode as JPEG with reduced quality and/or dimensions.
     */
    private function compressImage(string $imageData, string $contentType): ?string
    {
        $source = @imagecreatefromstring($imageData);
        if (! $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        // Scale down if very large (max 2048px on longest side)
        $maxDim = 2048;
        if ($width > $maxDim || $height > $maxDim) {
            $ratio = min($maxDim / $width, $maxDim / $height);
            $newWidth = (int) round($width * $ratio);
            $newHeight = (int) round($height * $ratio);
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        // Try quality levels until under 1MB
        foreach ([80, 65, 50] as $quality) {
            ob_start();
            imagejpeg($source, null, $quality);
            $output = ob_get_clean();

            if (strlen($output) <= self::MAX_BLOB_BYTES) {
                imagedestroy($source);

                return $output;
            }
        }

        imagedestroy($source);

        return null;
    }

    // ── Session ──

    private function createSession(string $identifier, string $password): ?array
    {
        $response = Http::timeout(15)
            ->post(self::API_BASE . '/com.atproto.server.createSession', [
                'identifier' => $identifier,
                'password' => $password,
            ]);

        if ($response->successful() && $response->json('accessJwt')) {
            return $response->json();
        }

        Log::warning('BlueskyDriver: createSession failed', [
            'identifier' => $identifier,
            'error' => $response->json('message') ?? $response->body(),
        ]);

        return null;
    }

    // ── Facets ──

    /**
     * Detect URLs in text and create facets with correct UTF-8 byte indices.
     */
    private function detectUrlFacets(string $text): array
    {
        $facets = [];
        $pattern = '/https?:\/\/[^\s\)\]\}>,]+/i';

        if (! preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return $facets;
        }

        foreach ($matches[0] as [$url, $byteStart]) {
            $url = rtrim($url, '.,;:!?)');
            $byteEnd = $byteStart + strlen($url);

            $facets[] = [
                'index' => [
                    'byteStart' => $byteStart,
                    'byteEnd' => $byteEnd,
                ],
                'features' => [
                    [
                        '$type' => 'app.bsky.richtext.facet#link',
                        'uri' => $url,
                    ],
                ],
            ];
        }

        return $facets;
    }
}
