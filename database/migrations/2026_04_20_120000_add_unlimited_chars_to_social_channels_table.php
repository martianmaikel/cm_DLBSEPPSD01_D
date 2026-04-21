<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_channels', function (Blueprint $table) {
            $table->boolean('unlimited_chars')->default(false)->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('social_channels', function (Blueprint $table) {
            $table->dropColumn('unlimited_chars');
        });
    }
};
