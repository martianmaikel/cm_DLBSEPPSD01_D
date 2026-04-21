<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_extractions', function (Blueprint $table) {
            $table->string('canonical_name')->nullable()->after('name');
            $table->text('role_context')->nullable()->after('canonical_name');
            $table->uuid('actor_id')->nullable()->after('role_context');

            $table->index('canonical_name');
            $table->index('actor_id');

            $table->foreign('actor_id')->references('id')->on('actors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entity_extractions', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
            $table->dropIndex(['actor_id']);
            $table->dropIndex(['canonical_name']);
            $table->dropColumn(['canonical_name', 'role_context', 'actor_id']);
        });
    }
};
