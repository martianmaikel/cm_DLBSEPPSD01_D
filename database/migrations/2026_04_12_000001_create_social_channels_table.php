<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_channels', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);  // threads, facebook, telegram
            $table->string('locale', 5);      // en, de, etc.
            $table->string('name', 100);      // "Clash Monitor", "Clash Monitor DE"
            $table->string('handle', 100);    // @username, channel ID, page ID
            $table->text('credentials');       // encrypted JSON
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('posts_event')->default(true);
            $table->boolean('posts_briefing')->default(true);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('daily_post_count')->default(0);
            $table->unsignedInteger('daily_post_limit')->default(50);
            $table->timestamp('last_posted_at')->nullable();
            $table->timestamps();

            $table->index(['platform', 'locale']);
            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_channels');
    }
};
