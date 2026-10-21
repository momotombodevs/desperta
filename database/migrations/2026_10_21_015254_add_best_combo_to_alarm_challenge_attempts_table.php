<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('alarm_challenge_attempts', function (Blueprint $table) {
            $table->unsignedTinyInteger('best_combo')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alarm_challenge_attempts', function (Blueprint $table) {
            $table->dropColumn('best_combo');
        });
    }
};
