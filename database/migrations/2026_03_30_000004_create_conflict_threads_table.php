<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conflict_threads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('summary')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conflict_threads');
    }
};
