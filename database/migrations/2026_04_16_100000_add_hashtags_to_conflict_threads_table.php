<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conflict_threads', function (Blueprint $table) {
            $table->jsonb('hashtags')->nullable()->after('categories');
        });
    }

    public function down(): void
    {
        Schema::table('conflict_threads', function (Blueprint $table) {
            $table->dropColumn('hashtags');
        });
    }
};
