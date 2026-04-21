<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_affiliates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('headline_en');
            $table->string('headline_de');
            $table->text('body_en')->nullable();
            $table->text('body_de')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('target_url', 2048);
            $table->string('cta_en', 60)->default('Learn more');
            $table->string('cta_de', 60)->default('Mehr erfahren');
            $table->string('utm_source', 100)->default('clashmonitor');
            $table->string('utm_medium', 100)->default('email');
            $table->string('utm_campaign', 100)->nullable();
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('impression_count')->default(0);
            $table->unsignedBigInteger('click_count')->default(0);
            $table->timestamps();

            $table->index(['active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_affiliates');
    }
};
