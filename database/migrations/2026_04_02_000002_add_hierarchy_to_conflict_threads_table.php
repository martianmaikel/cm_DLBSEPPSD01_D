<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conflict_threads', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('conflict_threads')
                ->nullOnDelete();

            $table->string('slug')->nullable()->unique()->after('name');
            $table->json('countries')->nullable()->after('status');
            $table->json('categories')->nullable()->after('countries');
            $table->unsignedInteger('event_count_24h')->default(0)->after('categories');
            $table->unsignedInteger('event_count_total')->default(0)->after('event_count_24h');
            $table->unsignedTinyInteger('max_severity')->default(0)->after('event_count_total');
            $table->unsignedInteger('sub_thread_count')->default(0)->after('max_severity');

            $table->index('parent_id');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::table('conflict_threads', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn([
                'parent_id',
                'slug',
                'countries',
                'categories',
                'event_count_24h',
                'event_count_total',
                'max_severity',
                'sub_thread_count',
            ]);
        });
    }
};
