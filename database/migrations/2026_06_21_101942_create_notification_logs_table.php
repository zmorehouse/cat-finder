<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->default('sms');
            $table->string('recipient')->nullable();
            // sent | failed | skipped
            $table->string('status')->default('sent');
            $table->text('body')->nullable();
            $table->string('provider_sid')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('animal_count')->default(0);
            // Snapshot of the animals this notification was about.
            $table->json('animals')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
