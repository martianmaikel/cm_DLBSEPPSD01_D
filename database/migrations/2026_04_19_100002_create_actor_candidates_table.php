<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actor_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('normalized_name');
            $table->enum('actor_type', ['person', 'organization']);
            $table->string('display_name');
            $table->jsonb('mention_events_json')->nullable();
            $table->unsignedInteger('event_count')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('blocked')->default(false);
            $table->timestamps();

            $table->unique(['normalized_name', 'actor_type']);
            $table->index('event_count');
            $table->index('blocked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actor_candidates');
    }
};
