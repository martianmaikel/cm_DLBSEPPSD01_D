<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_intelligence', function (Blueprint $table) {
            $table->string('country_code', 2)->primary();
            $table->string('country_name');
            $table->string('continent_slug')->nullable();
            $table->unsignedTinyInteger('threat_level')->default(0);
            $table->text('intelligence_briefing_en')->nullable();
            $table->text('intelligence_briefing_de')->nullable();
            $table->unsignedInteger('event_count_24h')->default(0);
            $table->unsignedInteger('event_count_total')->default(0);
            $table->unsignedTinyInteger('max_severity')->default(0);
            $table->decimal('avg_severity', 3, 1)->default(0.0);
            $table->json('category_breakdown')->nullable();
            $table->json('active_thread_ids')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_intelligence');
    }
};
