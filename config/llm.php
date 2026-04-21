<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default LLM Providers
    |--------------------------------------------------------------------------
    |
    | Classification and embedding use separate providers so they can be
    | independently configured (different rate limits, pricing, failure modes).
    |
    */

    'default_classifier' => env('LLM_CLASSIFIER', 'gemini'),
    'default_embedder' => env('LLM_EMBEDDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    */

    'providers' => [

        'grok' => [
            'api_key' => env('GROK_API_KEY'),
            'base_url' => env('GROK_BASE_URL', 'https://api.x.ai/v1'),
            'model' => env('GROK_MODEL', 'grok-3'),
            'embedding_model' => env('GROK_EMBEDDING_MODEL', 'grok-embedding'),
            'embedding_dimensions' => (int) env('GROK_EMBEDDING_DIMENSIONS', 1536),
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model' => env('GEMINI_MODEL', 'gemini-2.1-flash-lite-preview'),
            'analysis_model' => env('GEMINI_ANALYSIS_MODEL', 'gemini-3-flash-preview'),
            'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
            'embedding_dimensions' => (int) env('GEMINI_EMBEDDING_DIMENSIONS', 768),
        ],

        'claude' => [
            'api_key' => env('CLAUDE_API_KEY'),
            'base_url' => env('CLAUDE_BASE_URL', 'https://api.anthropic.com/v1'),
            'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
            'embedding_model' => env('CLAUDE_EMBEDDING_MODEL'),
            'embedding_dimensions' => (int) env('CLAUDE_EMBEDDING_DIMENSIONS', 1024),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Classification Prompt
    |--------------------------------------------------------------------------
    */

    'classification_prompt' => <<<'PROMPT'
You are a conflict event classifier for an OSINT monitoring platform.
Today's date is {current_date}.
Analyze the following raw report from source "{source_name}" ({source_type}).

--- RAW REPORT START ---
{raw_content}
--- RAW REPORT END ---

RELEVANCE: Return {"relevant": false} if the report has NO connection to armed conflict,
military affairs, or security. Examples of IRRELEVANT topics: sports, space exploration,
entertainment, celebrities, technology products, domestic legislation (LGBTQ laws,
pensions, housing policy), routine elections, weather, tourism.

Examples of RELEVANT topics (always classify these):
- Military operations, airstrikes, shelling, troop movements, casualties
- Terrorism, assassinations, bombings, hostage situations
- Sanctions, blockades, arms deals, military alliances (NATO, etc.)
- Peace talks, ceasefire negotiations, war crime investigations
- Nuclear weapons proliferation, military crashes in conflict zones
- War economy effects (oil prices from war, trade disruptions from sanctions)
- Refugee crises, humanitarian emergencies from conflict
- Cyber attacks on government/military/infrastructure
- Military buffer zones, occupations, territorial disputes

ENTITY EXTRACTION RULES (strict — follow precisely):
- "person": named individual humans only (e.g. Donald Trump, Itamar Ben-Gvir, Hassan
  Nasrallah, Volodymyr Zelensky). Do NOT list generic roles like "the president" or
  "a commander" — only include if a proper name is given in the source text.
- "organization": named non-state groups, political parties, armed groups, ministries,
  intelligence agencies, or international bodies (Hamas, Hezbollah, IDF, FSB, Mossad,
  NATO, UN Security Council, Taliban, Wagner Group's parent body, etc.).
  NEVER list sovereign countries or states as organizations. "United States", "Israel",
  "Iran", "Russia", "Ukraine", "China" etc. are LOCATIONS and MUST NOT appear in
  the entities array at all — they are already captured in the "country" field.
- "unit": specific named military sub-units, battalions, brigades or paramilitary
  formations (e.g. "Al-Qassam Brigades", "IRGC Quds Force", "36th Marine Brigade").
  Not generic terms like "the army", "troops" or "forces".
- Omit any entity you cannot clearly assign to one of these three types.
- Quality over quantity: prefer an empty array over polluting it with country names,
  generic roles, or vague collective nouns.
- For each person, include a "role_context" field with the role/title/affiliation as
  stated in the source (e.g. "Minister of National Security, Israel"). For
  organizations, include a brief function (e.g. "Lebanese militant group"). Use null
  only when the source gives no context at all.
- For each entity, include a "canonical_name" field — the most complete or official
  form of the name if obvious (e.g. "Donald Trump" for a mention of "Trump"). If the
  name in the source is already the canonical form, repeat it there.

If relevant, return ONLY valid JSON matching this schema:

{
  "relevant": true,
  "category": "war|terrorism|cyber|protest|disaster|diplomacy|economic",
  "subcategory": "<granular type: airstrike|artillery|ground_offensive|troop_movement|naval_operation|ceasefire_violation|territorial_change|terror_attack|lone_wolf|suicide_bombing|cyber_espionage|ransomware|ddos|data_breach|mass_protest|riot|crackdown|natural_disaster|industrial_disaster|humanitarian_crisis|peace_negotiation|ceasefire|arms_deal|sanctions|alliance|economic_collapse|trade_disruption|resource_conflict|other>",
  "severity": <1-10, where 1=minor incident, 5=significant engagement, 10=mass casualty/strategic shift>,
  "severity_factors": {
    "impact": <1-10, physical destruction and operational disruption>,
    "casualty": <1-10, confirmed or likely human casualties>,
    "escalation": <1-10, risk of widening conflict or retaliation>,
    "international": <1-10, cross-border implications or foreign involvement>
  },
  "confidence": <1-10, where 1=rumor/single anonymous source, 5=named source but unverified, 10=official confirmation with evidence>,
  "entities": [{"name": "<name as mentioned>", "type": "person|organization|unit", "canonical_name": "<official form>", "role_context": "<role/function or null>"}],
  "country": "<ISO 3166-1 alpha-2 code or null>",
  "region": "<subnational region name or null>",
  "latitude": <float or null>,
  "longitude": <float or null>,
  "title_en": "<Short English headline for the event. If the source is non-English, translate the core event into a concise English title. Max ~80 characters.>",
  "title_de": "<Short German headline for the event. Translate the core event into a concise German title. Max ~80 characters.>",
  "summary": "<1-2 sentences, neutral tone, no editorializing, factual>",
  "summary_de": "<German translation of the summary. 1-2 sentences, neutral tone, no editorializing, factual.>",
  "conflict_context": "<Name of the broader armed conflict or crisis this event belongs to. Use ESTABLISHED conflict names consistently: 'Russia-Ukraine War', 'Israeli-Palestinian Conflict', 'Iran-Israel Military Conflict', 'Syrian Civil War', 'Myanmar Civil War', 'Sudan Civil War', 'Yemen Conflict', 'Ethiopia Conflict', 'DR Congo Conflict', 'Somalia Conflict', 'US-Iran Conflict', 'Taiwan Strait Tensions', 'North Korea Tensions'. Use these exact names when applicable. Null ONLY if this is a truly isolated incident not part of any known conflict or crisis.>"
}

CATEGORY DEFINITIONS:
- "war": Armed conflict, military operations, airstrikes, artillery, ground offensives, naval engagements, troop movements, territorial changes, ceasefire violations
- "terrorism": Terror attacks by designated groups, lone-actor attacks, politically/ideologically motivated violence against civilians
- "cyber": Cyber attacks on critical infrastructure, state-sponsored hacking, ransomware on government/military systems, cyber warfare
- "protest": Large-scale protests, riots, strikes with security force response, civil unrest with political/security implications
- "disaster": Natural disasters with security implications (displacement into conflict zones, disruption of military operations), industrial disasters, humanitarian crises
- "diplomacy": Peace negotiations, ceasefire talks, arms control agreements, sanctions on belligerents, alliance developments directly related to armed conflicts
- "economic": Economic crises with direct security implications — currency collapses triggering unrest, trade disruptions in conflict regions, sanctions impacts, resource competition escalating to confrontation

CRITICAL RULES:
1. NEVER invent, fabricate, or hallucinate information. Every field you return MUST
   come directly from the raw report text. If the report says "earthquake in Indonesia",
   the country MUST be ID (Indonesia), NOT UA. The summary MUST describe an earthquake,
   NOT airstrikes. If details are missing from the report, use null — do NOT guess.
2. The "summary" field must be a faithful summary of the ACTUAL report content.
   Do not substitute a different event. Do not mix up countries or event types.
3. Severity measures event IMPACT. Confidence measures CERTAINTY. These are independent.
4. Do not infer coordinates from country alone. Only provide lat/lng when a specific
   city, town, base, or landmark is named. Otherwise return null for both.
5. The "diplomacy" and "economic" categories are ONLY for events with direct armed
   conflict or security implications. General political/economic news does NOT qualify.
6. If the raw report is extremely short (under ~20 words), contains only emojis/reactions,
   or is a comment/opinion without any factual event report, return {"relevant": false}.
   Only classify posts that describe an ACTUAL EVENT that has occurred or is occurring.
7. The "summary" MUST be a NEW condensed description of the report content — never copy
   the title verbatim. Write 1-2 neutral sentences that capture the key facts.
8. Do NOT add political titles or qualifiers (e.g. "Former President", "Ex-Prime Minister")
   from your training data. Use the title/role exactly as stated in the source text. If the
   source simply says a person's name without a title, do not add one. If you must reference
   a role for context, use the CURRENT role as of today's date, not outdated training data.
PROMPT,

];
