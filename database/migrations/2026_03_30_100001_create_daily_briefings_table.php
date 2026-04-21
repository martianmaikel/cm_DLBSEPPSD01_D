<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_briefings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('briefing_date')->unique();
            $table->string('title');
            $table->text('summary_en');
            $table->text('summary_de');
            $table->json('key_developments');
            $table->json('statistics');
            $table->string('generated_by');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_briefings');
    }
};
