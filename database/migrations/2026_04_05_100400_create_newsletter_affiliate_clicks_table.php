<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_affiliate_clicks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('affiliate_id')
                ->constrained('newsletter_affiliates')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('send_id')->nullable();
            $table->uuid('subscriber_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('clicked_at');

            $table->index('affiliate_id');
            $table->index('send_id');
            $table->index('clicked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_affiliate_clicks');
    }
};
