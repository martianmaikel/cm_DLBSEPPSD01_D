<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_ses_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('message_id', 255)->nullable();
            $table->string('event_type', 50); // Bounce | Complaint | Delivery | SubscriptionConfirmation
            $table->string('recipient_email', 255)->nullable();
            $table->jsonb('payload');
            $table->timestamp('received_at');

            $table->index('message_id');
            $table->index('event_type');
            $table->index('recipient_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_ses_events');
    }
};
