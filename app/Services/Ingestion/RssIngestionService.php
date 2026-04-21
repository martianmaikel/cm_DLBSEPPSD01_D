<?php

namespace App\Services\Ingestion;

use App\Contracts\SourceConnector;
use App\Jobs\ProcessRawEventJob;
use App\Models\Source;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RssIngestionService implements SourceConnector
{
    public function __construct(private readonly MediaExtractor $mediaExtractor) {}

    public function supports(Source $source): bool
    {
        return $source->type === 'rss';
    }

    public function poll(Source $source): void
    {
        try {
            $response = Http::connectTimeout(5)
                ->timeout(15)
                ->withUserAgent('Mozilla/5.0 (compatible; ClashMonitor/1.0)')
                ->get($source->url);

            if ($response->failed()) {
                Log::warning('RSS fetch failed', [
                    'source_id' => $source->id,
                    'source_name' => $source->name,
                    'status' => $response->status(),
                ]);

                return;
            }

            $xml = @simplexml_load_string($response->body());

            if ($xml === false) {
                Log::warning('RSS parse failed — invalid XML', [
                    'source_id' => $source->id,
                    'source_name' => $source->name,
                ]);

                return;
            }

            $items = $this->extractItems($xml);

            // Limit items per poll to avoid flooding the queue on first run
            // (Telegram feeds via RSSHub return full channel history with no Redis dedup)
            $maxItems = $source->connector_config['max_items'] ?? 20;
            $items = array_slice($items, 0, $maxItems);

            foreach ($items as $item) {
                $this->processItem($item, $source);
            }
        } catch (\Throwable $e) {
            Log::error('RSS ingestion error', [
                'source_id' => $source->id,
                'source_name' => $source->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractItems(\SimpleXMLElement $xml): array
    {
        // RSS 2.0: /rss/channel/item
        if (isset($xml->channel->item)) {
            $items = [];
            foreach ($xml->channel->item as $item) {
                $items[] = $item;
            }

            return $items;
        }

        // RDF/RSS 1.0: /rdf:RDF/item (used by DW and some legacy feeds)
        if (isset($xml->item)) {
            $items = [];
            foreach ($xml->item as $item) {
                $items[] = $item;
            }

            return $items;
        }

        // Atom: /feed/entry
        if (isset($xml->entry)) {
            $items = [];
            foreach ($xml->entry as $entry) {
                $items[] = $entry;
            }

            return $items;
        }

        return [];
    }

    private function processItem(\SimpleXMLElement $item, Source $source): void
    {
        $title = (string) ($item->title ?? '');
        $rawContent = (string) ($item->description ?? $item->summary ?? $item->content ?? '');
        $pubDate = (string) ($item->pubDate ?? $item->updated ?? $item->published ?? '');
        // RSS 2.0 / RDF: <link>https://...</link>
        // Atom: <link href="https://..." rel="alternate"/>
        $link = (string) ($item->link ?? '');
        if (empty($link) && isset($item->link['href'])) {
            $link = (string) $item->link['href'];
        }
        if (empty($link)) {
            $link = (string) ($item->id ?? '');
        }

        if (empty($title) && empty($rawContent)) {
            return;
        }

        $timestamp = $pubDate ? strtotime($pubDate) : time();

        // Dual-bucket deduplication: current + previous 5-minute bucket
        $currentBucket = (int) floor($timestamp / 300);
        $hashes = [
            md5($title . $source->id . $currentBucket),
            md5($title . $source->id . ($currentBucket - 1)),
        ];

        foreach ($hashes as $hash) {
            if (Redis::get("event_hash:{$hash}")) {
                return; // Already processed
            }
        }

        $canonicalHash = $hashes[0];
        Redis::setex("event_hash:{$canonicalHash}", 172800, '1'); // 48h TTL

        $mediaUrls = $this->mediaExtractor->fromHtml($rawContent, $link ?: null);

        // RSS 2.0 <enclosure url="..." type="image/jpeg" />
        if (isset($item->enclosure)) {
            $this->extractEnclosureMedia($item->enclosure, $mediaUrls);
        }

        // Media RSS namespace: <media:content url="..." medium="image" />
        // Used by Al Jazeera, BBC, Reuters, and most major news RSS feeds
        $this->extractMediaRssContent($item, $mediaUrls);

        ProcessRawEventJob::dispatch([
            'title' => $title,
            'raw_content' => $rawContent ?: $title,
            'source_id' => $source->id,
            'source_url' => $link ?: null,
            'media_urls' => $mediaUrls ?: null,
            'hash' => $canonicalHash,
            'occurred_at' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : now()->toDateTimeString(),
        ]);
    }

    /**
     * Extract media from RSS 2.0 <enclosure> elements.
     */
    private function extractEnclosureMedia(\SimpleXMLElement $enclosure, array &$mediaUrls): void
    {
        // SimpleXML: single <enclosure> is iterable (yields itself once)
        foreach ($enclosure as $enc) {
            $url = (string) ($enc['url'] ?? '');
            $type = (string) ($enc['type'] ?? '');
            if ($url === '') {
                continue;
            }
            if (str_starts_with($type, 'image/')) {
                $mediaUrls[] = ['url' => $url, 'type' => 'image'];
            } elseif (str_starts_with($type, 'video/')) {
                $mediaUrls[] = ['url' => $url, 'type' => 'video'];
            }
        }
    }

    /**
     * Extract media from Media RSS namespace (<media:content>, <media:thumbnail>).
     * This is the standard used by most major news feeds (Reuters, BBC, Al Jazeera, etc.).
     */
    private function extractMediaRssContent(\SimpleXMLElement $item, array &$mediaUrls): void
    {
        $namespaces = $item->getNamespaces(true);
        $mediaNamespace = $namespaces['media'] ?? null;

        if (! $mediaNamespace) {
            return;
        }

        $mediaElements = $item->children($mediaNamespace);
        if (! $mediaElements || $mediaElements->count() === 0) {
            return;
        }

        $countBefore = count($mediaUrls);

        // <media:content url="..." medium="image" type="image/jpeg" />
        // Attributes on namespaced elements require ->attributes() accessor
        foreach ($mediaElements->content ?? [] as $content) {
            $attrs = $content->attributes();
            $url = (string) ($attrs->url ?? '');
            if ($url === '') {
                continue;
            }
            $medium = (string) ($attrs->medium ?? '');
            $type = (string) ($attrs->type ?? '');
            if ($medium === 'image' || str_starts_with($type, 'image/')) {
                $mediaUrls[] = ['url' => $url, 'type' => 'image'];
            } elseif ($medium === 'video' || str_starts_with($type, 'video/')) {
                $mediaUrls[] = ['url' => $url, 'type' => 'video'];
            }
        }

        // <media:thumbnail url="..." /> — fallback if no media:content was found
        if (count($mediaUrls) === $countBefore) {
            foreach ($mediaElements->thumbnail ?? [] as $thumb) {
                $url = (string) ($thumb->attributes()->url ?? '');
                if ($url !== '') {
                    $mediaUrls[] = ['url' => $url, 'type' => 'image'];
                }
            }
        }
    }
}
