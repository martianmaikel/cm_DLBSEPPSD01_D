<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_extractions', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('name');
            $table->enum('type', ['person', 'unit', 'organization', 'location']);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->index('event_id');
            $table->index(['name', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_extractions');
    }
};
