<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Creates a SQLite-compatible schema for testing.
 *
 * The production migrations use PostGIS geography columns and pgvector which
 * are incompatible with SQLite. This trait builds a parallel schema that works
 * in the in-memory SQLite test environment. Coordinates are stored as two
 * nullable float columns (lat/lng) instead of a geography column.
 */
trait CreatesSqliteSchema
{
    protected function createTestSchema(): void
    {
        Schema::create('source_families', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('editorial_ownership')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('rss'); // enum: rss, telegram, manual
            $table->string('url')->nullable();
            $table->foreignId('source_family_id')->constrained('source_families')->cascadeOnDelete();
            $table->unsignedInteger('polling_interval')->default(10);
            $table->decimal('reliability_score', 3, 2)->default(0.50);
            $table->boolean('active')->default(true);
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('conflict_threads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('summary')->nullable();
            $table->string('status')->default('open'); // open, closed
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('raw_content');
            $table->string('category')->nullable();
            $table->unsignedTinyInteger('severity')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('status')->default('pending_classification');
            $table->string('country', 2)->nullable();
            $table->string('region')->nullable();
            $table->boolean('geo_approximate')->default(false);
            $table->float('lat')->nullable();   // replaces geography(Point)
            $table->float('lng')->nullable();   // replaces geography(Point)
            $table->timestamp('occurred_at')->nullable();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('conflict_thread_id')->nullable()->constrained('conflict_threads')->nullOnDelete();
            $table->unsignedInteger('corroboration_count')->default(0);
            $table->string('hash')->unique();
            $table->json('entities_json')->nullable();
            $table->unsignedTinyInteger('classification_attempts')->default(0);
            $table->timestamp('last_reconciled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('provider');
            $table->unsignedInteger('dimensions');
            $table->text('vector_json')->nullable(); // replaces pgvector column
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'provider']);
        });

        Schema::create('entity_extractions', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('name');
            $table->string('type')->default('other');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });

        Schema::create('corroboration_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_a_id');
            $table->uuid('event_b_id');
            $table->decimal('similarity_score', 6, 4)->default(0);
            $table->string('match_method')->default('embedding');
            $table->boolean('cross_family')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('event_a_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('event_b_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    protected function dropTestSchema(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('corroboration_links');
        Schema::dropIfExists('entity_extractions');
        Schema::dropIfExists('embeddings');
        Schema::dropIfExists('events');
        Schema::dropIfExists('conflict_threads');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('source_families');
        Schema::enableForeignKeyConstraints();
    }
}
