<?php

namespace App\Services\Ingestion;

use App\Contracts\SourceConnector;
use App\Jobs\ProcessRawEventJob;
use App\Models\Source;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class TelegramIngestionService implements SourceConnector
{
    public function __construct(private readonly MediaExtractor $mediaExtractor) {}

    public function supports(Source $source): bool
    {
        return $source->type === 'telegram';
    }

    private const TELEGRAM_API_BASE = 'https://api.telegram.org';

    public function poll(Source $source): void
    {
        $botToken = config('services.telegram.bot_token');

        if (empty($botToken)) {
            Log::warning('Telegram bot token not configured', ['source_id' => $source->id]);

            return;
        }

        // Retrieve the last processed update_id from Redis
        $offsetKey = "telegram:offset:{$source->id}";
        $offset = (int) (Redis::get($offsetKey) ?? 0);

        try {
            $response = Http::timeout(20)
                ->get(self::TELEGRAM_API_BASE . "/bot{$botToken}/getUpdates", [
                    'offset' => $offset ?: null,
                    'limit' => 100,
                    'timeout' => 10,
                    'allowed_updates' => ['channel_post', 'message'],
                ]);

            if ($response->failed()) {
                Log::warning('Telegram getUpdates failed', [
                    'source_id' => $source->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            $updates = $response->json('result', []);

            if (empty($updates)) {
                return;
            }

            // Merge album/media_group messages: Telegram delivers multi-photo/video
            // posts as separate updates sharing media_group_id, with the caption only
            // on the first one. Collapse them into a single logical message with all
            // media attached.
            $updates = $this->mergeMediaGroups($updates);

            foreach ($updates as $update) {
                $this->processUpdate($update, $source);
            }

            // Advance offset past the last processed update
            $lastUpdateId = end($updates)['update_id'] ?? null;
            if ($lastUpdateId !== null) {
                Redis::set($offsetKey, $lastUpdateId + 1);
            }
        } catch (\Throwable $e) {
            Log::error('Telegram ingestion error', [
                'source_id' => $source->id,
                'source_name' => $source->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processUpdate(array $update, Source $source): void
    {
        $message = $update['channel_post'] ?? $update['message'] ?? null;

        if (empty($message)) {
            return;
        }

        $text = $message['text'] ?? $message['caption'] ?? '';
        $messageId = $message['message_id'] ?? null;
        $timestamp = $message['date'] ?? time();

        if (empty($text) || empty($messageId)) {
            return;
        }

        $mediaUrls = $this->mediaExtractor->fromTelegramMessage($message);
        if (! empty($message['_album_extra_media'])) {
            $mediaUrls = array_merge($mediaUrls, $message['_album_extra_media']);
        }

        $title = $this->extractTitle($text);

        // Dual-bucket deduplication: current + previous 5-minute bucket
        $currentBucket = (int) floor($timestamp / 300);
        $hashes = [
            md5($title . $source->id . $currentBucket),
            md5($title . $source->id . ($currentBucket - 1)),
        ];

        foreach ($hashes as $hash) {
            if (Redis::get("event_hash:{$hash}")) {
                return;
            }
        }

        $canonicalHash = $hashes[0];
        Redis::setex("event_hash:{$canonicalHash}", 172800, '1'); // 48h TTL

        ProcessRawEventJob::dispatch([
            'title' => $title,
            'raw_content' => $text,
            'source_id' => $source->id,
            'hash' => $canonicalHash,
            'media_urls' => $mediaUrls ?: null,
            'occurred_at' => date('Y-m-d H:i:s', $timestamp),
        ]);
    }

    /**
     * Collapse Telegram media-group updates (albums) into a single update.
     * The first message in the group carries the caption; siblings only carry
     * additional photos/videos. We merge all sibling media into the first
     * message's photo/video arrays so the caller sees one event with N media.
     */
    private function mergeMediaGroups(array $updates): array
    {
        $groups = [];
        $result = [];

        foreach ($updates as $update) {
            $message = $update['channel_post'] ?? $update['message'] ?? null;
            $groupId = $message['media_group_id'] ?? null;

            if (! $groupId) {
                $result[] = $update;
                continue;
            }

            if (! isset($groups[$groupId])) {
                // First of this group — keep as-is and remember where it lives
                $groups[$groupId] = count($result);
                $result[] = $update;
                continue;
            }

            $primaryIndex = $groups[$groupId];
            $primaryKey = isset($result[$primaryIndex]['channel_post']) ? 'channel_post' : 'message';

            // Append this sibling's media to the primary message
            if (! empty($message['photo'])) {
                $result[$primaryIndex][$primaryKey]['_album_photos'][] = $message['photo'];
            }
            if (! empty($message['video'])) {
                $result[$primaryIndex][$primaryKey]['_album_videos'][] = $message['video'];
            }
        }

        // Flatten album extras back onto the primary message so MediaExtractor sees them.
        // We convert them into a synthetic document list the extractor already handles.
        foreach ($groups as $primaryIndex) {
            $key = isset($result[$primaryIndex]['channel_post']) ? 'channel_post' : 'message';
            $msg = &$result[$primaryIndex][$key];

            $extraItems = [];
            foreach ($msg['_album_photos'] ?? [] as $photoSizes) {
                $largest = end($photoSizes);
                if (! empty($largest['file_id'])) {
                    $extraItems[] = ['type' => 'image', 'telegram_file_id' => $largest['file_id']];
                }
            }
            foreach ($msg['_album_videos'] ?? [] as $video) {
                if (! empty($video['file_id'])) {
                    $extraItems[] = ['type' => 'video', 'telegram_file_id' => $video['file_id']];
                }
            }
            $msg['_album_extra_media'] = $extraItems;
            unset($msg['_album_photos'], $msg['_album_videos']);
        }

        return $result;
    }

    private function extractTitle(string $text): string
    {
        // First line, trimmed, max 200 chars
        $lines = explode("\n", trim($text));
        $firstLine = trim($lines[0]);

        return mb_substr($firstLine ?: $text, 0, 200);
    }
}
