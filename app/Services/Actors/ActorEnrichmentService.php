<?php

namespace App\Services\Actors;

use App\Models\Actor;
use App\Models\EntityExtraction;
use App\Services\AiUsageTracker;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use RuntimeException;

class ActorEnrichmentService
{
    public function enrich(Actor $actor): void
    {
        $mode = $this->resolveMode();
        $context = $this->buildContext($actor);
        $wiki = app(WikipediaLookupService::class)->fetchByName(
            $actor->canonical_name,
            array_slice($actor->aliases ?? [], 0, 5),
        );

        $systemInstruction = $this->buildSystemInstruction($actor, $mode, $wiki !== null);
        $userPrompt = $this->buildUserPrompt($actor, $context, $wiki);

        $data = $this->callProvider($actor, $systemInstruction, $userPrompt, $mode);

        $this->applyEnrichment($actor, $data, $mode, $wiki);
    }

    private function resolveMode(): string
    {
        $mode = (string) config('actors.enrichment_mode', 'llm_knowledge');
        return in_array($mode, ['event_only', 'llm_knowledge', 'web_search'], true)
            ? $mode
            : 'llm_knowledge';
    }

    /**
     * @return array{events: array<int, array<string, mixed>>, aliases: array<int, string>}
     */
    private function buildContext(Actor $actor): array
    {
        $limit = (int) config('actors.enrichment_max_events_in_context', 20);

        $extractions = EntityExtraction::where('actor_id', $actor->id)
            ->with(['event' => fn ($q) => $q->select(
                'id', 'title', 'summary', 'occurred_at', 'country', 'region', 'category', 'subcategory'
            )])
            ->get();

        $events = $extractions
            ->filter(fn ($e) => $e->event !== null)
            ->sortByDesc(fn ($e) => optional($e->event->occurred_at)->getTimestamp() ?? 0)
            ->take($limit)
            ->map(fn ($e) => [
                'occurred_at' => optional($e->event->occurred_at)?->toDateString(),
                'country' => $e->event->country,
                'region' => $e->event->region,
                'category' => $e->event->category,
                'subcategory' => $e->event->subcategory,
                'title' => $e->event->title,
                'summary' => $e->event->summary,
                'role_context' => $e->role_context,
            ])
            ->values()
            ->all();

        $aliases = $extractions
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return ['events' => $events, 'aliases' => $aliases];
    }

    private function buildSystemInstruction(Actor $actor, string $mode, bool $hasWikipedia = false): string
    {
        $today = now()->toDateString();

        $base = "You are a research analyst building a structured dossier on a conflict-relevant "
            . ($actor->actor_type === 'person' ? 'person' : 'organization')
            . " for an OSINT monitoring platform. Today's date is {$today}. Return neutral, factual data — "
            . "no editorializing. If a field cannot be determined with confidence, return null for it.";

        $sourcePriority = $hasWikipedia
            ? "- The attached WIKIPEDIA SUMMARY is always kept current by human editors and is the PRIMARY "
                . "ground-truth source. When it conflicts with your pre-trained knowledge, the Wikipedia text wins.\n"
                . "- Event summaries are secondary evidence; role_context fields from events confirm or refine "
                . "what Wikipedia says.\n"
            : "- The attached event summaries and their role_context fields reflect reality as of {$today} and "
                . "OVERRIDE your pre-trained knowledge.\n";

        $currencyRules = "\n\nCURRENCY RULES (critical):\n"
            . "- Your training data has a cutoff date and is almost certainly out of date for current political/military roles.\n"
            . $sourcePriority
            . "- Do NOT prefix roles with 'Former', 'Ex-', 'ehemalig' etc. unless the attached sources EXPLICITLY "
            . "state the person is no longer in that role.\n"
            . "- For role_title / org_type: use what the attached sources indicate, not what your training data assumes.\n"
            . "- For status: default to 'active' unless the sources explicitly indicate death, dissolution, "
            . "retirement, or equivalent.\n"
            . "- summary_short, summary_long, and relevance_summary must describe the actor's CURRENT role and "
            . "relevance. Do not write historical biography with phrases like 'served as', 'was the', 'former' "
            . "unless the sources make clear the role is in the past.";

        return match ($mode) {
            'event_only' => $base . ' You may ONLY use information provided in the attached event summaries. '
                . 'Do not use your pre-trained knowledge. If the events do not establish a fact, return null.'
                . $currencyRules,
            'web_search' => $base . ' Use the attached event summaries together with your pre-trained knowledge. '
                . 'If tool-based web search becomes available, cite source URLs in sources_json.'
                . $currencyRules,
            default /* llm_knowledge */ => $base . ' You may use your pre-trained knowledge in addition to the attached '
                . 'event summaries, BUT the events take precedence whenever they conflict with pre-trained knowledge '
                . '(especially for current roles and status). Use pre-trained knowledge only to fill gaps the events '
                . 'do not cover (e.g. birth year, affiliations from long ago).'
                . $currencyRules,
        };
    }

    private function buildUserPrompt(Actor $actor, array $context, ?array $wiki = null): string
    {
        $canonical = $actor->canonical_name;
        $type = $actor->actor_type;
        $aliasList = empty($context['aliases']) ? '(none)' : implode(', ', $context['aliases']);

        $wikiBlock = '';
        if ($wiki !== null) {
            $wikiBlock = "WIKIPEDIA SUMMARY (primary ground-truth source, language={$wiki['language']}, URL: {$wiki['url']}):\n"
                . ($wiki['description'] ? "Short description: {$wiki['description']}\n" : '')
                . "Extract:\n{$wiki['extract']}\n\n";
        }

        $roleContexts = [];
        $eventsBlock = '';
        foreach ($context['events'] as $i => $e) {
            $n = $i + 1;
            $date = $e['occurred_at'] ?? 'unknown';
            $loc = trim(($e['region'] ?? '') . ' ' . ($e['country'] ?? ''));
            $category = trim(($e['category'] ?? '') . ' / ' . ($e['subcategory'] ?? ''), ' /');
            $role = $e['role_context'] ? " [role: {$e['role_context']}]" : '';

            if ($e['role_context']) {
                $roleContexts[$date . '|' . $e['role_context']] = [
                    'date' => $date,
                    'role' => $e['role_context'],
                ];
            }

            $eventsBlock .= "({$n}) {$date} | {$loc} | {$category}{$role}\n"
                . "    Title: " . ($e['title'] ?? '') . "\n"
                . "    Summary: " . ($e['summary'] ?? '') . "\n\n";
        }

        if ($eventsBlock === '') {
            $eventsBlock = "(no event summaries available)\n";
        }

        $roleBlock = '';
        if (! empty($roleContexts)) {
            usort($roleContexts, fn ($a, $b) => strcmp($b['date'], $a['date']));
            $roleBlock = "Role mentions from sources (most recent first — these reflect reality at each date and OVERRIDE pre-trained knowledge):\n";
            foreach ($roleContexts as $rc) {
                $roleBlock .= "  - {$rc['date']}: {$rc['role']}\n";
            }
            $roleBlock .= "\n";
        }

        $authoritativeNote = $wiki !== null
            ? "\nReturn the structured dossier now. The WIKIPEDIA SUMMARY is authoritative — derive role_title, "
                . "status, dates, and summaries primarily from it. Event mentions provide additional conflict context."
            : "\nReturn the structured dossier now. Roles from the events above are authoritative for the current period.";

        return "Build a dossier for this {$type}:\n"
            . "Canonical name: {$canonical}\n"
            . "Known aliases/name variants from coverage: {$aliasList}\n\n"
            . $wikiBlock
            . $roleBlock
            . "Event mentions (most recent first):\n\n"
            . $eventsBlock
            . $authoritativeNote;
    }

    private function callProvider(Actor $actor, string $system, string $prompt, string $mode): array
    {
        [$provider, $providerName, $model] = $this->resolveProvider();

        $tracker = app(AiUsageTracker::class);
        $startTime = hrtime(true);

        try {
            $response = Prism::structured()
                ->using($provider, $model)
                ->withSchema($this->enrichmentSchema($actor->actor_type))
                ->withSystemPrompt($system)
                ->withPrompt($prompt)
                ->usingTemperature(0.2)
                ->withMaxTokens(2048)
                ->withClientOptions(['timeout' => 60, 'connect_timeout' => 10])
                ->asStructured();

            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            $tokensIn = $response->usage->inputTokens ?? AiUsageTracker::estimateTokens($prompt);
            $tokensOut = $response->usage->outputTokens ?? AiUsageTracker::estimateTokens(json_encode($response->structured));
            $tracker->log($providerName, $model, 'enrich_actor', $tokensIn, $tokensOut, $latencyMs);

            if (! is_array($response->structured)) {
                throw new RuntimeException('Structured response was not an array');
            }

            return $response->structured;
        } catch (PrismException $e) {
            $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
            $tracker->log($providerName, $model, 'enrich_actor', AiUsageTracker::estimateTokens($prompt), 0, $latencyMs, 'error', $e->getMessage());
            Log::warning('Actor enrichment via Prism failed', [
                'actor_id' => $actor->id,
                'provider' => $providerName,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Actor enrichment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array{0: Provider, 1: string, 2: string}
     */
    private function resolveProvider(): array
    {
        $default = (string) config('llm.default_classifier', 'gemini');

        return match ($default) {
            'claude' => [Provider::Anthropic, 'claude', (string) config('llm.providers.claude.model')],
            'grok' => [Provider::XAI, 'grok', (string) config('llm.providers.grok.model')],
            'gemini' => [Provider::Gemini, 'gemini', (string) config('llm.providers.gemini.model')],
            default => throw new InvalidArgumentException("Unknown LLM provider for actor enrichment: {$default}"),
        };
    }

    private function enrichmentSchema(string $actorType): ObjectSchema
    {
        $commonProps = [
            new StringSchema('summary_short', 'One neutral sentence for cards and lists.', nullable: true),
            new StringSchema('summary_long', 'One paragraph factual description.', nullable: true),
            new StringSchema('relevance_summary', 'Why this actor matters in the context of conflict monitoring (1-2 sentences).', nullable: true),
            new StringSchema('country', 'Primary country of affiliation as ISO 3166-1 alpha-2 (e.g. US, IL, RU).', nullable: true),
            new StringSchema('region', 'Subnational region name', nullable: true),
            new ArraySchema(
                name: 'categories',
                description: 'Short tags describing this actor (e.g. ["politician","head_of_state"] or ["militia","islamist"]).',
                items: new StringSchema('category', 'Category tag'),
            ),
            new EnumSchema('status', 'Current status', ['active', 'inactive', 'deceased', 'dissolved', 'unknown']),
            new NumberSchema('confidence', 'How confident you are this is a clearly identifiable real actor (1-10)', minimum: 1, maximum: 10),
            new ArraySchema(
                name: 'aliases',
                description: 'Alternative names and spellings.',
                items: new StringSchema('alias', 'Alternative name'),
            ),
            new ArraySchema(
                name: 'sources_json',
                description: 'URLs or citations for claims (empty array if none).',
                items: new StringSchema('source', 'Source URL or citation'),
            ),
        ];

        if ($actorType === 'person') {
            $typeProps = [
                new StringSchema('full_name', 'Full canonical name', nullable: true),
                new StringSchema('role_title', 'Current or most-recent notable role, e.g. "President of Russia"', nullable: true),
                new NumberSchema('birth_year', 'Birth year if known', nullable: true),
                new NumberSchema('death_year', 'Death year if deceased', nullable: true),
                new StringSchema('nationality', 'ISO 3166-1 alpha-2', nullable: true),
            ];
            $required = ['status', 'confidence', 'categories', 'aliases', 'sources_json'];
        } else {
            $typeProps = [
                new EnumSchema('org_type', 'Organization type', [
                    'government', 'military', 'militia', 'armed_group', 'political_party',
                    'terrorist_group', 'intelligence_agency', 'ngo', 'international_body',
                ]),
                new NumberSchema('founded_year', 'Year founded if known', nullable: true),
                new NumberSchema('dissolved_year', 'Year dissolved if applicable', nullable: true),
                new StringSchema('headquarters_country', 'ISO 3166-1 alpha-2', nullable: true),
            ];
            $required = ['status', 'confidence', 'categories', 'aliases', 'sources_json', 'org_type'];
        }

        return new ObjectSchema(
            name: 'actor_dossier',
            description: 'Structured dossier for a conflict-relevant ' . $actorType,
            properties: array_merge($commonProps, $typeProps),
            requiredFields: $required,
        );
    }

    private function applyEnrichment(Actor $actor, array $data, string $mode, ?array $wiki = null): void
    {
        $existingAliases = $actor->aliases ?? [];
        $newAliases = $data['aliases'] ?? [];
        $mergedAliases = array_values(array_unique(array_map('strval', array_merge($existingAliases, $newAliases))));

        $sources = is_array($data['sources_json'] ?? null) ? array_values($data['sources_json']) : [];
        if ($wiki !== null && ! in_array($wiki['url'], $sources, true)) {
            array_unshift($sources, $wiki['url']);
        }

        $updates = [
            'summary_short' => $data['summary_short'] ?? null,
            'summary_long' => $data['summary_long'] ?? null,
            'relevance_summary' => $data['relevance_summary'] ?? null,
            'country' => $this->sanitizeIso($data['country'] ?? null),
            'region' => $data['region'] ?? null,
            'categories' => is_array($data['categories'] ?? null) ? array_values($data['categories']) : null,
            'status' => in_array($data['status'] ?? null, ['active', 'inactive', 'deceased', 'dissolved', 'unknown'], true)
                ? $data['status']
                : 'unknown',
            'confidence' => isset($data['confidence']) ? max(1, min(10, (int) $data['confidence'])) : null,
            'aliases' => $mergedAliases,
            'sources_json' => $sources,
            'enrichment_status' => 'enriched',
            'enrichment_mode_used' => $mode,
            'enriched_at' => now(),
        ];

        if (empty($actor->image_url) && $wiki !== null && ! empty($wiki['thumbnail_url'])) {
            $updates['image_url'] = $wiki['thumbnail_url'];
        }

        // Respect admin-locked fields: do not overwrite fields the user has manually curated.
        $locked = is_array($actor->locked_fields) ? $actor->locked_fields : [];
        foreach ($locked as $field) {
            unset($updates[$field]);
        }

        if ($actor->actor_type === 'person') {
            $updates = array_merge($updates, [
                'full_name' => $data['full_name'] ?? null,
                'role_title' => $data['role_title'] ?? null,
                'birth_year' => isset($data['birth_year']) ? (int) $data['birth_year'] : null,
                'death_year' => isset($data['death_year']) ? (int) $data['death_year'] : null,
                'nationality' => $this->sanitizeIso($data['nationality'] ?? null),
            ]);
        } else {
            $orgTypes = ['government', 'military', 'militia', 'armed_group', 'political_party',
                'terrorist_group', 'intelligence_agency', 'ngo', 'international_body'];
            $updates = array_merge($updates, [
                'org_type' => in_array($data['org_type'] ?? null, $orgTypes, true) ? $data['org_type'] : null,
                'founded_year' => isset($data['founded_year']) ? (int) $data['founded_year'] : null,
                'dissolved_year' => isset($data['dissolved_year']) ? (int) $data['dissolved_year'] : null,
                'headquarters_country' => $this->sanitizeIso($data['headquarters_country'] ?? null),
            ]);
        }

        $actor->update($updates);

        Log::info('Actor enriched', [
            'actor_id' => $actor->id,
            'mode' => $mode,
            'confidence' => $updates['confidence'],
        ]);
    }

    private function sanitizeIso(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $v = strtoupper(trim($value));
        return preg_match('/^[A-Z]{2}$/', $v) ? $v : null;
    }
}
