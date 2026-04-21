<?php

namespace App\Services\Ingestion;

class MediaExtractor
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'm4v'];

    private const MAX_ITEMS = 10;

    /**
     * Parse an HTML fragment (RSS description, Atom content, etc.) and return
     * a list of embedded media items. Each item is ['url' => string, 'type' => 'image'|'video'].
     * Dedupes by URL, drops data: URIs and relative paths without a base.
     */
    public function fromHtml(?string $html, ?string $baseUrl = null): array
    {
        if (empty($html) || ! str_contains($html, '<')) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        // Wrap in a root element and encoding hint so DOMDocument handles UTF-8 correctly
        $wrapped = '<?xml encoding="UTF-8"?><div>' . $html . '</div>';
        $loaded = $doc->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $items = [];
        $seen = [];

        $collect = function (string $rawUrl, string $type) use (&$items, &$seen, $baseUrl) {
            $url = $this->normalizeUrl($rawUrl, $baseUrl);
            if ($url === null || isset($seen[$url])) {
                return;
            }
            $seen[$url] = true;
            $items[] = ['url' => $url, 'type' => $type];
        };

        foreach ($doc->getElementsByTagName('img') as $img) {
            /** @var \DOMElement $img */
            $src = $img->getAttribute('src');
            if ($src !== '') {
                $collect($src, 'image');
            }
        }

        foreach ($doc->getElementsByTagName('source') as $source) {
            /** @var \DOMElement $source */
            $src = $source->getAttribute('src');
            if ($src === '') {
                continue;
            }
            $parent = $source->parentNode?->nodeName ?? '';
            $type = $parent === 'video' ? 'video' : $this->guessTypeFromUrl($src);
            if ($type) {
                $collect($src, $type);
            }
        }

        foreach ($doc->getElementsByTagName('video') as $video) {
            /** @var \DOMElement $video */
            $src = $video->getAttribute('src');
            if ($src !== '') {
                $collect($src, 'video');
            }
            $poster = $video->getAttribute('poster');
            if ($poster !== '') {
                $collect($poster, 'image');
            }
        }

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    /**
     * Extract media from a Telegram Bot API message object.
     * Telegram media URLs require the bot token and expire, so we store the
     * file_id and resolve it lazily at post time via a dedicated driver.
     *
     * Returns entries shaped as ['type' => 'image'|'video', 'telegram_file_id' => string].
     */
    public function fromTelegramMessage(array $message): array
    {
        $items = [];

        // photo is an array of PhotoSize variants — take the largest (last entry)
        if (! empty($message['photo']) && is_array($message['photo'])) {
            $largest = end($message['photo']);
            if (is_array($largest) && ! empty($largest['file_id'])) {
                $items[] = ['type' => 'image', 'telegram_file_id' => $largest['file_id']];
            }
        }

        if (! empty($message['video']['file_id'])) {
            $items[] = ['type' => 'video', 'telegram_file_id' => $message['video']['file_id']];
        }

        if (! empty($message['animation']['file_id'])) {
            $items[] = ['type' => 'video', 'telegram_file_id' => $message['animation']['file_id']];
        }

        if (! empty($message['document']['mime_type']) && ! empty($message['document']['file_id'])) {
            $mime = $message['document']['mime_type'];
            if (str_starts_with($mime, 'image/')) {
                $items[] = ['type' => 'image', 'telegram_file_id' => $message['document']['file_id']];
            } elseif (str_starts_with($mime, 'video/')) {
                $items[] = ['type' => 'video', 'telegram_file_id' => $message['document']['file_id']];
            }
        }

        return $items;
    }

    private function normalizeUrl(string $url, ?string $baseUrl): ?string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, 'data:') || str_starts_with($url, 'cid:')) {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        // Protocol-relative
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if ($baseUrl && preg_match('#^(https?://[^/]+)#i', $baseUrl, $m)) {
            if (str_starts_with($url, '/')) {
                return $m[1] . $url;
            }
        }

        return null;
    }

    private function guessTypeFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            return 'image';
        }
        if (in_array($ext, self::VIDEO_EXTENSIONS, true)) {
            return 'video';
        }

        return null;
    }
}
