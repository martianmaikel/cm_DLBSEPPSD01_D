<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);          // gemini, grok, claude
            $table->string('model', 80);              // gemini-3-flash-preview, grok-3, etc.
            $table->string('operation', 30);           // classify, embed, batch_embed, briefing, threat_level
            $table->unsignedInteger('tokens_input')->default(0);
            $table->unsignedInteger('tokens_output')->default(0);
            $table->decimal('estimated_cost', 10, 6)->default(0);  // in USD
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('status', 20)->default('success');      // success, error
            $table->string('error_message', 500)->nullable();
            $table->uuid('event_id')->nullable();
            $table->unsignedSmallInteger('batch_size')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['provider', 'created_at']);
            $table->index(['operation', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
