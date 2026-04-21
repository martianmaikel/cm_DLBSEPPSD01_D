<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_sends', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('subscriber_id');
            $table->string('type', 30); // confirm | daily_global | thread_digest | critical_alert | test
            $table->string('send_key', 150)->unique(); // e.g. daily_global:{uuid}:{YYYY-MM-DD}
            $table->text('subject')->nullable();
            $table->string('locale', 5)->nullable();
            $table->unsignedBigInteger('affiliate_id')->nullable();
            $table->string('ses_message_id', 255)->nullable();
            $table->enum('status', [
                'queued',
                'sent',
                'bounced',
                'complained',
                'failed',
            ])->default('queued');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            $table->foreign('subscriber_id')
                ->references('id')->on('newsletter_subscribers')
                ->cascadeOnDelete();

            $table->index(['subscriber_id', 'type', 'sent_at']);
            $table->index('type');
            $table->index('ses_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_sends');
    }
};
