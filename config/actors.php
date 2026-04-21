<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Promotion Threshold
    |--------------------------------------------------------------------------
    |
    | Number of distinct events in which a name must be mentioned before it is
    | promoted from the actor_candidates staging table into the actors table.
    |
    */
    'promotion_threshold' => (int) env('ACTORS_PROMOTION_THRESHOLD', 3),

    /*
    |--------------------------------------------------------------------------
    | Enrichment Mode
    |--------------------------------------------------------------------------
    |
    | Controls how the EnrichActorJob builds its prompt:
    |   - event_only: only uses summaries of events where the actor was mentioned
    |   - llm_knowledge: additionally allows the LLM to use its pre-trained knowledge
    |   - web_search: additionally enables web-search tool usage (requires provider support)
    |
    */
    'enrichment_mode' => env('ACTORS_ENRICHMENT_MODE', 'llm_knowledge'),

    /*
    |--------------------------------------------------------------------------
    | Refresh Cadence
    |--------------------------------------------------------------------------
    |
    | Actors whose enriched_at is older than this value will be re-enriched by
    | the scheduled RefreshStaleActorsJob.
    |
    */
    'refresh_after_days' => (int) env('ACTORS_REFRESH_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Enrichment Event Context
    |--------------------------------------------------------------------------
    |
    | Max number of event summaries included as context for the enrichment LLM call.
    |
    */
    'enrichment_max_events_in_context' => (int) env('ACTORS_MAX_EVENTS_CONTEXT', 20),

    /*
    |--------------------------------------------------------------------------
    | Similarity Threshold
    |--------------------------------------------------------------------------
    |
    | pg_trgm similarity cutoff for fuzzy-matching an incoming entity name to
    | an existing actor when no exact alias match is found.
    |
    */
    'similarity_threshold' => (float) env('ACTORS_SIMILARITY_THRESHOLD', 0.7),

    /*
    |--------------------------------------------------------------------------
    | Generic Terms Blocklist
    |--------------------------------------------------------------------------
    |
    | Names that are too generic to represent a real actor. Case-insensitive,
    | matched after whitespace normalization.
    |
    */
    'name_blocklist' => [
        'the government', 'government', 'the military', 'military',
        'the army', 'army', 'the navy', 'navy', 'the air force', 'air force',
        'authorities', 'officials', 'forces', 'troops', 'soldiers',
        'insurgents', 'rebels', 'militants', 'terrorists', 'civilians',
        'police', 'security forces', 'armed forces', 'intelligence',
        'president', 'prime minister', 'minister', 'commander', 'general',
        'spokesperson', 'spokesman', 'spokeswoman',
        'unknown', 'unidentified', 'anonymous',
    ],
];
