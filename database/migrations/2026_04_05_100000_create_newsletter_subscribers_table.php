<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('timezone', 64)->default('UTC');
            $table->string('locale', 5)->default('en'); // en | de
            $table->enum('status', [
                'pending',
                'confirmed',
                'unsubscribed',
                'bounced',
                'complained',
            ])->default('pending');

            // Tokens (permanent, not session)
            $table->string('confirm_token', 64)->unique()->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirm_ip', 45)->nullable();
            $table->string('unsubscribe_token', 64)->unique();
            $table->string('preferences_token', 64)->unique();

            // Preferences
            $table->boolean('wants_global_digest')->default(true);

            // Tracking
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedSmallInteger('bounce_count')->default(0);
            $table->timestamp('unsubscribed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'timezone']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
