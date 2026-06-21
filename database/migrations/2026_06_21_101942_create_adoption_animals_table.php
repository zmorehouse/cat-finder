<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adoption_animals', function (Blueprint $table) {
            $table->id();
            // Stable Salesforce/AnimalOS id used to detect "new" listings.
            $table->string('external_id')->unique();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->string('breed')->nullable();
            $table->string('age')->nullable();
            $table->string('site')->nullable();
            $table->string('status')->nullable();
            $table->string('url')->nullable();
            // Whether we have already notified about this specific animal.
            $table->boolean('notified')->default(false);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adoption_animals');
    }
};
