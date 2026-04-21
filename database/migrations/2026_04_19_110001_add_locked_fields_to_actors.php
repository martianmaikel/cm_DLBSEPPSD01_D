<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actors', function (Blueprint $table) {
            $table->jsonb('locked_fields')->nullable()->after('sources_json');
        });
    }

    public function down(): void
    {
        Schema::table('actors', function (Blueprint $table) {
            $table->dropColumn('locked_fields');
        });
    }
};
