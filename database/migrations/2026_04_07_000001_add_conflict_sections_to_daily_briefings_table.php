<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_briefings', function (Blueprint $table) {
            $table->json('conflict_sections')->nullable()->after('key_developments');
        });
    }

    public function down(): void
    {
        Schema::table('daily_briefings', function (Blueprint $table) {
            $table->dropColumn('conflict_sections');
        });
    }
};
