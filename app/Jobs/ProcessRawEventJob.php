<?php

namespace App\Jobs;

use App\Contracts\ClassificationProvider;
use App\Models\EntityExtraction;
use App\Models\Event;
use App\Models\Source;
use App\Services\Actors\ActorResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessRawEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(private readonly array $payload) {}

    public function handle(ClassificationProvider $classifier, ActorResolver $actorResolver): void
    {
        $hash = $this->payload['hash'];

        // Pre-dispatch DB dedup check (events.hash has a unique index)
        $existing = Event::where('hash', $hash)->first();

        if ($existing && $existing->status !== 'pending_classification') {
            Log::debug('ProcessRawEventJob skipped — duplicate hash', ['hash' => $hash]);

            return;
        }

        // If retrying a pending_classification event, remove it so we can reclassify
        if ($existing) {
            Log::info('ProcessRawEventJob retrying pending_classification', ['event_id' => $existing->id, 'hash' => $hash]);
            $existing->delete();
        }

        $sourceId = $this->payload['source_id'];
        $source = Source::with('sourceFamily')->find($sourceId);

        if (! $source) {
            Log::error('ProcessRawEventJob: source not found', ['source_id' => $sourceId]);

            return;
        }

        $sourceContext = "{$source->name}|{$source->type}";

        // Track source quality metrics in Redis (24h rolling window)
        $sourceKey = "source_stats:{$source->id}";
        Redis::hincrby($sourceKey, 'total', 1);
        Redis::expire($sourceKey, 86400);

        // Minimum content filter: skip items too short to classify (memes, emoji-only, reactions)
        $strippedContent = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}\x{200D}\x{20E3}\x{E0020}-\x{E007F}]/u', '', $this->payload['raw_content']);
        $strippedContent = trim(preg_replace('/\s+/', ' ', $strippedContent));

        if (mb_strlen($strippedContent) < 30) {
            Log::debug('ProcessRawEventJob skipped — content too short for classification', [
                'hash' => $hash,
                'title' => $this->payload['title'],
                'content_length' => mb_strlen($strippedContent),
            ]);

            return;
        }

        // Keyword pre-filter: skip items with zero conflict signal before calling the AI.
        // OSINT/Telegram sources are pre-curated, so only apply to broad sources (api type = GDELT).
        if ($source->type === 'api' && ! $this->hasConflictSignal($strippedContent)) {
            Redis::hincrby($sourceKey, 'prefiltered', 1);
            Log::debug('ProcessRawEventJob skipped — no conflict signal in content', [
                'hash' => $hash,
                'source' => $source->name,
                'title' => $this->payload['title'],
            ]);

            return;
        }

        try {
            $result = $classifier->classify(
                rawContent: $this->payload['raw_content'],
                sourceContext: $sourceContext,
            );

            if (! $result->relevant) {
                Redis::hincrby($sourceKey, 'irrelevant', 1);
                Log::debug('ProcessRawEventJob skipped — not conflict-relevant', [
                    'hash' => $hash,
                    'source' => $source->name,
                    'title' => $this->payload['title'],
                ]);

                return;
            }

            // Secondary relevance gate: catch borderline events the LLM let through
            if ($result->category === 'other' && $result->severity < 3 && $result->confidence < 4) {
                Redis::hincrby($sourceKey, 'irrelevant', 1);
                Log::debug('ProcessRawEventJob skipped — failed secondary relevance gate', [
                    'hash' => $hash,
                    'source' => $source->name,
                    'title' => $this->payload['title'],
                    'category' => $result->category,
                    'severity' => $result->severity,
                    'confidence' => $result->confidence,
                ]);

                return;
            }

            // Hallucination guard: if the LLM summary has zero overlap with the title,
            // the classification is untrustworthy. Prefer the LLM-translated title_en
            // when available — it shares the English language domain with the summary.
            // Comparing a Cyrillic/Arabic title against an English summary would always
            // produce zero matches and generate false positives.
            $compareTitle = $result->titleEn ?: $this->payload['title'];

            if ($result->summary && $compareTitle) {
                $stopWords = ['the', 'a', 'an', 'in', 'on', 'at', 'to', 'of', 'and', 'or', 'is', 'was',
                    'are', 'for', 'with', 'from', 'by', 'as', 'how', 'what', 'who', 'its', 'has', 'after',
                    'been', 'this', 'that', 'will', 'says', 'said', 'new', 'not', 'but', 'about', 'into'];
                $summaryLower = mb_strtolower($result->summary);

                // Extract keywords from the comparison title (title_en if translated, else original)
                $titleLower = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', '', $compareTitle));
                $titleWords = preg_split('/\s+/', $titleLower, -1, PREG_SPLIT_NO_EMPTY);
                $titleKeyWords = array_values(array_filter($titleWords, fn ($w) => mb_strlen($w) > 3 && ! in_array($w, $stopWords)));
                $titleKeyWords = array_slice($titleKeyWords, 0, 5);

                $allKeyWords = $titleKeyWords;

                // Fall back to raw_content keywords only when we don't have a translated title
                // (i.e. the original title is presumed English, so raw_content is too).
                if (! $result->titleEn) {
                    $contentLower = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', '', $this->payload['raw_content']));
                    $contentWords = preg_split('/\s+/', $contentLower, -1, PREG_SPLIT_NO_EMPTY);
                    $contentKeyWords = array_values(array_filter($contentWords, fn ($w) => mb_strlen($w) > 3 && ! in_array($w, $stopWords)));
                    $contentKeyWords = array_slice($contentKeyWords, 0, 10);
                    $allKeyWords = array_unique(array_merge($titleKeyWords, $contentKeyWords));
                }

                if (count($allKeyWords) >= 2) {
                    $matches = array_filter($allKeyWords, fn ($w) => str_contains($summaryLower, $w));
                    if (count($matches) === 0) {
                        Redis::hincrby($sourceKey, 'irrelevant', 1);
                        Log::warning('ProcessRawEventJob skipped — hallucination detected', [
                            'hash' => $hash,
                            'source' => $source->name,
                            'title' => $this->payload['title'],
                            'title_en' => $result->titleEn,
                            'hallucinated_summary' => $result->summary,
                        ]);

                        return;
                    }
                }
            }

            // Semantic dedup: aggregator sources (e.g. GDELT) ingest multiple articles
            // about the same incident from different publishers (reuters.com, bbc.co.uk, etc.).
            // We keep only one event but track unique publisher domains as corroboration.
            $newTitle = $result->titleEn ?: $this->payload['title'];
            $existingEvent = $this->findSemanticDuplicate($newTitle, $result->category, $result->country, $source);

            if ($existingEvent) {
                $this->trackDomainCorroboration($existingEvent, $this->payload['source_url'] ?? null);

                Redis::hincrby($sourceKey, 'deduped_semantic', 1);
                Log::debug('ProcessRawEventJob skipped — semantic duplicate, domain corroboration tracked', [
                    'hash' => $hash,
                    'source' => $source->name,
                    'title' => $newTitle,
                    'existing_event_id' => $existingEvent->id,
                ]);

                return;
            }

            $cleanedEntities = $actorResolver->sanitizeEntities($result->entities);

            $event = DB::transaction(function () use ($result, $source, $hash, $cleanedEntities) {
                $event = Event::create([
                    'title' => $result->titleEn ?: $this->payload['title'],
                    'title_de' => $result->titleDe,
                    'summary' => $result->summary,
                    'summary_de' => $result->summaryDe,
                    'raw_content' => $this->payload['raw_content'],
                    'category' => $result->category,
                    'subcategory' => $result->subcategory,
                    'severity' => $result->severity,
                    'severity_factors' => $result->severityFactors,
                    'confidence' => $result->confidence,
                    'status' => 'unverified',
                    'country' => $result->country,
                    'region' => $result->region,
                    'geo_approximate' => true,
                    'occurred_at' => $this->payload['occurred_at'] ?? now(),
                    'source_id' => $source->id,
                    'source_url' => $this->payload['source_url'] ?? null,
                    'corroboration_count' => 0,
                    'hash' => $hash,
                    'entities_json' => array_merge(
                        $result->entities,
                        $result->conflictContext ? ['conflict_context' => $result->conflictContext] : [],
                    ),
                    'media_urls' => $this->payload['media_urls'] ?? null,
                    'classification_attempts' => 1,
                ]);

                foreach ($cleanedEntities as $entity) {
                    EntityExtraction::create([
                        'event_id' => $event->id,
                        'name' => $entity['name'],
                        'canonical_name' => $entity['canonical_name'] ?? null,
                        'role_context' => $entity['role_context'] ?? null,
                        'type' => $entity['type'],
                    ]);
                }

                return $event;
            });

            // Dispatch geocoding — embedding is handled by the scheduled batch job.
            // afterCommit() ensures the event row is visible to the worker.
            GeocodeEventJob::dispatch($event->id, $result->latitude, $result->longitude)->afterCommit();

            // Resolve extracted persons/orgs to actors or candidates (non-critical path).
            ResolveActorJob::dispatch($event->id)->afterCommit();

            Redis::hincrby($sourceKey, 'accepted', 1);
            Log::info('ProcessRawEventJob succeeded', ['event_id' => $event->id, 'source' => $source->name, 'hash' => $hash]);
        } catch (\Throwable $e) {
            Redis::hincrby($sourceKey, 'failed', 1);
            Log::error('ProcessRawEventJob classification failed', [
                'hash' => $hash,
                'source' => $source->name,
                'error' => $e->getMessage(),
            ]);

            // Store raw event with pending_classification status for retry
            $this->storePendingEvent($source, $hash);

            // Do not re-throw — we handled it by storing the pending event
        }
    }

    /**
     * Cheap keyword check to avoid wasting AI tokens on obviously irrelevant GDELT articles.
     * Matches against multilingual conflict vocabulary. Only needs ONE hit to pass.
     */
    private function hasConflictSignal(string $content): bool
    {
        // Lowercase for matching — covers title + metadata appended by connectors
        $text = mb_strtolower($content);

        $keywords = [
            // English
            'military', 'airstrike', 'air strike', 'missile', 'drone', 'strike', 'bomb',
            'shell', 'artillery', 'troops', 'soldier', 'combat', 'offensive', 'attack',
            'casualt', 'killed', 'wounded', 'dead', 'death toll', 'civilian', 'massacre',
            'genocide', 'war crime', 'ceasefire', 'cease-fire', 'armistice', 'invasion',
            'occupation', 'siege', 'blockade', 'embargo', 'sanction', 'militant', 'rebel',
            'insurgent', 'militia', 'guerrilla', 'terrorist', 'hostage', 'kidnap', 'ambush',
            'assassination', 'explosion', 'detonate', 'weapons', 'ammunition', 'arms deal',
            'nuclear', 'warhead', 'nato', 'pentagon', 'defense ministry', 'frontline',
            'battlefield', 'conflict', 'warfare', 'escalat', 'retaliat', 'incursion',
            'refugee', 'displaced', 'humanitarian crisis', 'war ', 'clashes', 'troops',
            'airstrike', 'shelling', 'intercept',
            // Russian / Ukrainian
            'удар', 'ракет', 'обстрел', 'дрон', 'бомб', 'атак', 'погиб', 'ранен',
            'военн', 'войск', 'фронт', 'наступлен', 'оборон', 'взрыв', 'эвакуац',
            'конфликт', 'война', 'терро', 'санкц', 'блокад',
            // Ukrainian specific
            'збройн', 'ворог', 'окупант', 'зенітн', 'безпілотн',
            // Arabic
            'قصف', 'صاروخ', 'هجوم', 'غارة', 'شهيد', 'قتل', 'حرب', 'عسكري', 'جيش', 'دمار',
            // French
            'frappe', 'bombardement', 'missile', 'troupes', 'militaire', 'attentat',
            'attaque', 'conflit', 'guerre', 'soldat', 'obus', 'roquette',
            // German
            'angriff', 'rakete', 'drohne', 'bombardier', 'militär', 'truppen', 'gefecht',
            'krieg', 'konflikt', 'beschuss', 'luftangriff',
            // Spanish
            'ataque', 'misil', 'bombardeo', 'tropas', 'militar', 'muertos', 'guerra',
            'conflicto', 'soldado', 'ofensiva',
            // Portuguese
            'atacar', 'ataque', 'míssil', 'bombardeio', 'tropas', 'militar', 'guerra',
            'conflito', 'mortos', 'ofensiva', 'soldado',
            // Italian
            'attacco', 'attacchi', 'missile', 'bombardamento', 'truppe', 'militare',
            'guerra', 'conflitto', 'soldati', 'offensiva', 'uccisi',
            // Turkish
            'saldırı', 'füze', 'bomba', 'askeri', 'çatışma', 'savaş', 'ordu', 'hava saldırısı',
            // Polish
            'atak', 'rakiet', 'bomba', 'wojsk', 'wojn', 'konflikt', 'żołnierz', 'ostrzał',
            // Czech / Slovak
            'útok', 'raketa', 'bomba', 'vojsk', 'válk', 'konflikt', 'voják',
            // Greek
            'επίθεση', 'πόλεμ', 'στρατ', 'βομβαρδ', 'πύραυλ', 'σύγκρουση',
            // Hindi
            'हमला', 'मिसाइल', 'सेना', 'युद्ध', 'सैनिक', 'बम',
            // Persian
            'حمله', 'موشک', 'جنگ', 'نظامی', 'بمب',
        ];

        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find a semantically equivalent event from the same source.
     * Returns the existing event if found, null otherwise.
     */
    private function findSemanticDuplicate(string $title, string $category, ?string $country, Source $source): ?Event
    {
        $query = Event::query()
            ->where('source_id', $source->id)
            ->where('category', $category)
            ->where('status', '!=', 'pending_classification')
            ->where('created_at', '>=', now()->subHours(24));

        if ($country) {
            $query->where('country', $country);
        }

        $candidates = $query->get(['id', 'title', 'source_url', 'status', 'corroboration_count']);

        if ($candidates->isEmpty()) {
            return null;
        }

        $titleLower = mb_strtolower($title);

        foreach ($candidates as $candidate) {
            $candidateLower = mb_strtolower($candidate->title);

            similar_text($titleLower, $candidateLower, $percent);
            if ($percent >= 55) {
                return $candidate;
            }

            if ($this->fuzzyTitleJaccard($titleLower, $candidateLower) >= 0.45) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Track unique publisher domains for aggregator corroboration.
     * GDELT articles reference different publishers (reuters.com, bbc.co.uk, etc.).
     * Each unique domain counts as an independent corroboration source.
     */
    private function trackDomainCorroboration(Event $existingEvent, ?string $newSourceUrl): void
    {
        if (! $newSourceUrl) {
            return;
        }

        $newDomain = $this->extractBaseDomain($newSourceUrl);
        if (! $newDomain) {
            return;
        }

        // Initialize domain set with the existing event's domain on first call
        $domainKey = "event_domains:{$existingEvent->id}";
        if (! Redis::exists($domainKey)) {
            $existingDomain = $this->extractBaseDomain($existingEvent->source_url ?? '');
            if ($existingDomain) {
                Redis::sadd($domainKey, $existingDomain);
            }
        }

        $added = Redis::sadd($domainKey, $newDomain);
        Redis::expire($domainKey, 172800); // 48h TTL

        if (! $added) {
            return; // Domain already counted
        }

        // Unique domains minus the original = corroboration count
        $uniqueDomains = Redis::scard($domainKey);
        $corroborationCount = max($existingEvent->corroboration_count, $uniqueDomains - 1);

        // Only upgrade status, never downgrade
        if ($corroborationCount >= 2 && in_array($existingEvent->status, ['unverified', 'corroborated'])) {
            $existingEvent->update(['status' => 'confirmed', 'corroboration_count' => $corroborationCount]);
        } elseif ($corroborationCount >= 1 && $existingEvent->status === 'unverified') {
            $existingEvent->update(['status' => 'corroborated', 'corroboration_count' => $corroborationCount]);
        } elseif ($corroborationCount > $existingEvent->corroboration_count) {
            $existingEvent->update(['corroboration_count' => $corroborationCount]);
        }

        Log::debug('Domain corroboration tracked', [
            'event_id' => $existingEvent->id,
            'new_domain' => $newDomain,
            'unique_domains' => $uniqueDomains,
            'corroboration_count' => $corroborationCount,
        ]);
    }

    private function extractBaseDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return null;
        }

        return preg_replace('/^www\./', '', mb_strtolower($host));
    }

    /**
     * Jaccard similarity on significant words (4+ characters) with fuzzy prefix matching.
     * Words sharing a 5-character prefix are treated as equivalent to handle
     * morphological variants across languages (e.g. "attack"/"attacking").
     */
    private function fuzzyTitleJaccard(string $a, string $b): float
    {
        $extract = fn (string $s) => array_values(array_unique(array_filter(
            preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', '', $s), -1, PREG_SPLIT_NO_EMPTY),
            fn ($w) => mb_strlen($w) >= 4
        )));

        $wordsA = $extract($a);
        $wordsB = $extract($b);

        if (empty($wordsA) || empty($wordsB)) {
            return 0.0;
        }

        $matched = [];
        foreach ($wordsA as $wa) {
            foreach ($wordsB as $wb) {
                if (isset($matched[$wb])) {
                    continue;
                }
                if ($wa === $wb || (mb_strlen($wa) >= 5 && mb_strlen($wb) >= 5
                    && mb_substr($wa, 0, 5) === mb_substr($wb, 0, 5))) {
                    $matched[$wb] = true;
                    break;
                }
            }
        }

        $intersectionCount = count($matched);
        $unionCount = count($wordsA) + count($wordsB) - $intersectionCount;

        return $unionCount > 0 ? $intersectionCount / $unionCount : 0.0;
    }

    private function storePendingEvent(Source $source, string $hash): void
    {
        try {
            // Use upsert to avoid duplicate key errors on retry
            $existing = Event::where('hash', $hash)->first();

            if ($existing) {
                $existing->increment('classification_attempts');

                return;
            }

            Event::create([
                'title' => $this->payload['title'],
                'summary' => '',
                'raw_content' => $this->payload['raw_content'],
                'category' => 'other',
                'severity' => 1,
                'confidence' => 1,
                'status' => 'pending_classification',
                'occurred_at' => $this->payload['occurred_at'] ?? now(),
                'source_id' => $source->id,
                'source_url' => $this->payload['source_url'] ?? null,
                'media_urls' => $this->payload['media_urls'] ?? null,
                'corroboration_count' => 0,
                'hash' => $hash,
                'classification_attempts' => 1,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessRawEventJob: failed to store pending event', [
                'hash' => $hash,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
