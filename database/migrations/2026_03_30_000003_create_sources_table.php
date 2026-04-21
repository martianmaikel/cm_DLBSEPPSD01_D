<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['rss', 'telegram', 'manual']);
            $table->string('url')->nullable();
            $table->foreignId('source_family_id')->constrained('source_families')->cascadeOnDelete();
            $table->unsignedInteger('polling_interval')->default(10); // minutes
            $table->decimal('reliability_score', 3, 2)->default(0.50);
            $table->boolean('active')->default(true);
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();

            $table->index('active');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
