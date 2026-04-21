<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('provider'); // grok, gemini, claude
            $table->unsignedInteger('dimensions');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->index('event_id');
            $table->unique(['event_id', 'provider']);
        });

        // Add pgvector column — 1536 dimensions for Grok default
        DB::statement('ALTER TABLE embeddings ADD COLUMN vector vector(1536)');
    }

    public function down(): void
    {
        Schema::dropIfExists('embeddings');
    }
};
