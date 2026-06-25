<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adoption_animals', function (Blueprint $table) {
            // Stamped when an animal no longer appears in the RSPCA API (adopted/removed).
            $table->timestamp('removed_at')->nullable()->after('first_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('adoption_animals', function (Blueprint $table) {
            $table->dropColumn('removed_at');
        });
    }
};
