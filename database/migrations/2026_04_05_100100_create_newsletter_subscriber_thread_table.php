<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscriber_thread', function (Blueprint $table) {
            $table->uuid('subscriber_id');
            $table->foreignId('conflict_thread_id')
                ->constrained('conflict_threads')
                ->cascadeOnDelete();
            $table->boolean('wants_digest')->default(true);
            $table->boolean('wants_critical')->default(true);
            $table->timestamps();

            $table->primary(['subscriber_id', 'conflict_thread_id']);
            $table->index('conflict_thread_id');

            $table->foreign('subscriber_id')
                ->references('id')->on('newsletter_subscribers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscriber_thread');
    }
};
