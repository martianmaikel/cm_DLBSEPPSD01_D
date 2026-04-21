<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_channel_id')->constrained('social_channels')->cascadeOnDelete();
            $table->string('postable_type');
            $table->uuid('postable_id');
            $table->string('post_key', 200)->unique();
            $table->string('platform', 20);
            $table->string('locale', 5);
            $table->text('content_text')->nullable();
            $table->string('platform_post_id', 255)->nullable();
            $table->string('status', 20)->default('queued'); // queued, published, failed, skipped
            $table->text('error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['postable_type', 'postable_id']);
            $table->index(['social_channel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
