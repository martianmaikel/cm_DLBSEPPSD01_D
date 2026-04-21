<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationships', function (Blueprint $table) {
            $table->id();

            $table->enum('from_type', ['actor', 'country', 'conflict', 'event']);
            $table->string('from_id');

            $table->enum('to_type', ['actor', 'country', 'conflict', 'event']);
            $table->string('to_id');

            $table->string('relation_type');
            $table->boolean('directed')->default(true);
            $table->decimal('weight', 3, 2)->nullable();

            $table->enum('source', ['derived', 'manual', 'ai'])->default('derived');

            $table->jsonb('evidence_json')->nullable();
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_to')->nullable();
            $table->jsonb('metadata_json')->nullable();

            $table->timestamps();

            $table->index(['from_type', 'from_id']);
            $table->index(['to_type', 'to_id']);
            $table->index('relation_type');
            $table->index('source');

            $table->unique(
                ['from_type', 'from_id', 'to_type', 'to_id', 'relation_type', 'source'],
                'relationships_unique_edge',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationships');
    }
};
