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
        Schema::create('daily_questions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('challenge_theme');
            $table->json('question');
            $table->string('selected_option')->nullable();
            $table->boolean('correct')->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_questions');
    }
};
