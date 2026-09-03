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
        Schema::create('alarms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('time', 5);
            $table->string('label');
            $table->json('weekdays');
            $table->boolean('vibration')->default(true);
            $table->boolean('snooze_enabled')->default(true);
            $table->string('difficulty');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarms');
    }
};
