<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('raw_content');
            $table->string('category')->nullable();
            $table->unsignedTinyInteger('severity')->nullable(); // 1-10
            $table->unsignedTinyInteger('confidence')->nullable(); // 1-10
            $table->enum('status', [
                'unverified',
                'corroborated',
                'confirmed',
                'disputed',
                'retracted',
                'pending_classification',
            ])->default('pending_classification');
            $table->string('country', 2)->nullable(); // ISO 3166-1 alpha-2
            $table->string('region')->nullable();
            $table->boolean('geo_approximate')->default(false);
            $table->timestamp('occurred_at')->nullable();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('conflict_thread_id')->nullable()->constrained('conflict_threads')->nullOnDelete();
            $table->unsignedInteger('corroboration_count')->default(0);
            $table->string('hash')->unique();
            $table->jsonb('entities_json')->nullable();
            $table->unsignedTinyInteger('classification_attempts')->default(0);
            $table->timestamp('last_reconciled_at')->nullable();
            $table->timestamps();

            $table->index('source_id');
            $table->index('conflict_thread_id');
            $table->index('country');
            $table->index('category');
            $table->index('status');
            $table->index('occurred_at');
        });

        // Add PostGIS geography column
        DB::statement('ALTER TABLE events ADD COLUMN coordinates geography(Point, 4326)');
        DB::statement('CREATE INDEX events_coordinates_idx ON events USING GIST (coordinates)');
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
