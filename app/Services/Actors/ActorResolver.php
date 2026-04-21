<?php

namespace App\Services\Actors;

use App\Models\Actor;
use Illuminate\Support\Facades\DB;

class ActorResolver
{
    private const HONORIFICS = [
        'his excellency', 'her excellency', 'the honorable', 'honorable',
        'former president', 'former prime minister', 'former foreign minister',
        'former minister', 'former secretary', 'former chancellor',
        'president', 'vice president', 'prime minister', 'deputy prime minister',
        'foreign minister', 'defense minister', 'defence minister',
        'interior minister', 'finance minister', 'secretary of state',
        'secretary', 'minister', 'chancellor', 'ambassador',
        'lieutenant general', 'major general', 'brigadier general',
        'general', 'colonel', 'lieutenant colonel', 'major',
        'captain', 'lieutenant', 'lt.', 'lt', 'sergeant', 'sgt.', 'sgt',
        'admiral', 'commander', 'commandant', 'chief',
        'senator', 'sen.', 'representative', 'rep.', 'congressman', 'congresswoman',
        'king', 'queen', 'prince', 'princess', 'sultan', 'emir', 'sheikh',
        'ayatollah', 'grand ayatollah', 'rabbi', 'imam', 'pope', 'patriarch',
        'dr.', 'dr', 'mr.', 'mr', 'mrs.', 'mrs', 'ms.', 'ms',
        'sir', 'lady', 'lord',
    ];

    /**
     * Country names (English) and common variants to block from entity extraction
     * when the LLM mistakenly classifies them as organizations.
     */
    private const COUNTRY_NAMES = [
        'united states', 'united states of america', 'usa', 'u.s.', 'u.s.a.', 'us', 'america',
        'russia', 'russian federation', 'ukraine', 'belarus', 'moldova', 'georgia',
        'israel', 'palestine', 'gaza', 'west bank', 'lebanon', 'syria', 'jordan',
        'iran', 'iraq', 'saudi arabia', 'yemen', 'qatar', 'uae', 'united arab emirates',
        'bahrain', 'kuwait', 'oman', 'turkey', 'türkiye',
        'egypt', 'libya', 'tunisia', 'algeria', 'morocco', 'sudan', 'south sudan',
        'ethiopia', 'eritrea', 'somalia', 'kenya', 'uganda', 'rwanda', 'burundi',
        'dr congo', 'democratic republic of the congo', 'congo', 'central african republic',
        'cameroon', 'nigeria', 'niger', 'mali', 'burkina faso', 'chad', 'senegal',
        'ivory coast', 'cote d\'ivoire', 'ghana', 'guinea', 'liberia', 'sierra leone',
        'mozambique', 'madagascar', 'zimbabwe', 'zambia', 'angola', 'namibia',
        'south africa', 'botswana', 'tanzania',
        'china', 'taiwan', 'hong kong', 'japan', 'south korea', 'north korea', 'korea',
        'mongolia', 'india', 'pakistan', 'bangladesh', 'nepal', 'bhutan', 'sri lanka',
        'maldives', 'afghanistan', 'myanmar', 'burma', 'thailand', 'vietnam', 'laos',
        'cambodia', 'malaysia', 'singapore', 'indonesia', 'philippines', 'brunei',
        'east timor', 'timor-leste', 'papua new guinea',
        'australia', 'new zealand', 'fiji', 'samoa', 'tonga',
        'germany', 'france', 'united kingdom', 'uk', 'great britain', 'britain', 'england',
        'scotland', 'wales', 'northern ireland', 'ireland', 'netherlands', 'belgium',
        'luxembourg', 'italy', 'spain', 'portugal', 'switzerland', 'austria',
        'poland', 'czech republic', 'czechia', 'slovakia', 'hungary', 'romania', 'bulgaria',
        'slovenia', 'croatia', 'bosnia', 'bosnia and herzegovina', 'serbia', 'montenegro',
        'kosovo', 'north macedonia', 'macedonia', 'albania', 'greece', 'cyprus', 'malta',
        'sweden', 'norway', 'denmark', 'finland', 'iceland', 'estonia', 'latvia', 'lithuania',
        'canada', 'mexico', 'guatemala', 'honduras', 'el salvador', 'nicaragua', 'costa rica',
        'panama', 'cuba', 'haiti', 'dominican republic', 'jamaica',
        'colombia', 'venezuela', 'ecuador', 'peru', 'bolivia', 'brazil', 'argentina',
        'chile', 'uruguay', 'paraguay', 'guyana', 'suriname',
        'armenia', 'azerbaijan', 'kazakhstan', 'uzbekistan', 'turkmenistan', 'tajikistan',
        'kyrgyzstan',
    ];

    /**
     * Filter entity array: drop countries, generics, too-short names, and location-typed
     * entries. Returns a cleaned list preserving only actor-worthy persons, organizations,
     * and military units.
     *
     * @param  array<int, array<string, mixed>>  $entities
     * @return array<int, array<string, mixed>>
     */
    public function sanitizeEntities(array $entities): array
    {
        $blocklist = array_map('mb_strtolower', config('actors.name_blocklist', []));
        $countries = array_flip(self::COUNTRY_NAMES);

        $cleaned = [];
        foreach ($entities as $entity) {
            if (! is_array($entity)) {
                continue;
            }

            $name = trim((string) ($entity['name'] ?? ''));
            $type = (string) ($entity['type'] ?? '');

            if ($name === '' || mb_strlen($name) < 3 || ctype_digit($name)) {
                continue;
            }

            if (! in_array($type, ['person', 'organization', 'unit'], true)) {
                continue;
            }

            $normalized = mb_strtolower(preg_replace('/\s+/', ' ', $name));

            if (isset($countries[$normalized])) {
                continue;
            }

            if (in_array($normalized, $blocklist, true)) {
                continue;
            }

            $canonical = isset($entity['canonical_name']) && trim((string) $entity['canonical_name']) !== ''
                ? trim((string) $entity['canonical_name'])
                : $name;

            $roleContext = isset($entity['role_context']) && trim((string) $entity['role_context']) !== ''
                ? trim((string) $entity['role_context'])
                : null;

            $cleaned[] = [
                'name' => $name,
                'type' => $type,
                'canonical_name' => $canonical,
                'role_context' => $roleContext,
            ];
        }

        return $cleaned;
    }

    /**
     * Strip honorifics, collapse whitespace, lowercase.
     */
    public function normalizeName(string $name): string
    {
        $n = mb_strtolower(trim($name));
        $n = preg_replace('/[\.,]/u', '', $n);
        $n = preg_replace('/\s+/u', ' ', $n);

        // Strip honorifics iteratively (e.g. "former president donald trump" → "donald trump")
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach (self::HONORIFICS as $prefix) {
                if (str_starts_with($n, $prefix . ' ')) {
                    $n = trim(substr($n, strlen($prefix) + 1));
                    $changed = true;
                    break;
                }
            }
        }

        return $n;
    }

    /**
     * Find an existing Actor that matches the given name.
     * Order: exact canonical_name → exact alias → pg_trgm similarity.
     */
    public function matchActor(string $name, string $type): ?Actor
    {
        $normalized = $this->normalizeName($name);

        if ($normalized === '') {
            return null;
        }

        $exact = Actor::where('actor_type', $type)
            ->whereRaw('LOWER(canonical_name) = ?', [$normalized])
            ->first();

        if ($exact) {
            return $exact;
        }

        // Alias match — search the JSONB aliases column for a case-insensitive equal value
        $aliasMatch = Actor::where('actor_type', $type)
            ->whereRaw("EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(aliases, '[]'::jsonb)) AS a WHERE LOWER(a) = ?)", [$normalized])
            ->first();

        if ($aliasMatch) {
            return $aliasMatch;
        }

        // pg_trgm fuzzy match
        $threshold = (float) config('actors.similarity_threshold', 0.7);

        $fuzzy = DB::selectOne(
            'SELECT id, similarity(LOWER(canonical_name), ?) AS sim
             FROM actors
             WHERE actor_type = ?
               AND similarity(LOWER(canonical_name), ?) > ?
             ORDER BY sim DESC
             LIMIT 1',
            [$normalized, $type, $normalized, $threshold]
        );

        return $fuzzy ? Actor::find($fuzzy->id) : null;
    }
}
