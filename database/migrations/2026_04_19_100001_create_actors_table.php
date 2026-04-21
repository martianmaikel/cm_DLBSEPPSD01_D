<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('actors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->enum('actor_type', ['person', 'organization']);

            $table->string('canonical_name');
            $table->jsonb('aliases')->nullable();

            $table->string('country', 2)->nullable();
            $table->string('region')->nullable();

            $table->text('summary_short')->nullable();
            $table->text('summary_long')->nullable();
            $table->text('relevance_summary')->nullable();

            $table->jsonb('categories')->nullable();
            $table->enum('status', ['active', 'inactive', 'deceased', 'dissolved', 'unknown'])->default('active');
            $table->unsignedTinyInteger('confidence')->nullable();

            $table->string('image_url')->nullable();
            $table->jsonb('sources_json')->nullable();

            $table->timestamp('first_mentioned_at')->nullable();
            $table->timestamp('last_mentioned_at')->nullable();
            $table->unsignedInteger('mention_count')->default(0);
            $table->unsignedInteger('event_count')->default(0);

            $table->enum('enrichment_status', ['pending', 'enriching', 'enriched', 'failed'])->default('pending');
            $table->enum('enrichment_mode_used', ['event_only', 'llm_knowledge', 'web_search'])->nullable();
            $table->timestamp('enriched_at')->nullable();
            $table->timestamp('promoted_at')->nullable();

            // Person-specific
            $table->string('full_name')->nullable();
            $table->string('role_title')->nullable();
            $table->uuid('affiliation_actor_id')->nullable();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->unsignedSmallInteger('death_year')->nullable();
            $table->string('nationality', 2)->nullable();

            // Organization-specific
            $table->enum('org_type', [
                'government', 'military', 'militia', 'armed_group', 'political_party',
                'terrorist_group', 'intelligence_agency', 'ngo', 'international_body',
            ])->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->unsignedSmallInteger('dissolved_year')->nullable();
            $table->string('headquarters_country', 2)->nullable();
            $table->uuid('parent_actor_id')->nullable();

            $table->timestamps();

            $table->index('actor_type');
            $table->index('canonical_name');
            $table->index('country');
            $table->index('enrichment_status');
            $table->index(['actor_type', 'country']);
        });

        // Self-referential FKs must be added after the table (and its PK) exist.
        Schema::table('actors', function (Blueprint $table) {
            $table->foreign('affiliation_actor_id')->references('id')->on('actors')->nullOnDelete();
            $table->foreign('parent_actor_id')->references('id')->on('actors')->nullOnDelete();
        });

        DB::statement('CREATE INDEX actors_canonical_name_trgm_idx ON actors USING gin (canonical_name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('actors');
    }
};
