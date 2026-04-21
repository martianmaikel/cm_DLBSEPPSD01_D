<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corroboration_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_a_id');
            $table->uuid('event_b_id');
            $table->decimal('similarity_score', 5, 4);
            $table->enum('match_method', ['embedding', 'entity', 'manual']);
            $table->boolean('cross_family')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('event_a_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('event_b_id')->references('id')->on('events')->cascadeOnDelete();
            $table->unique(['event_a_id', 'event_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corroboration_links');
    }
};
