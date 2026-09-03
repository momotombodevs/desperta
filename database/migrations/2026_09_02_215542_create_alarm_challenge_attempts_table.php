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
        Schema::create('alarm_challenge_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('alarm_id')->nullable()->constrained()->nullOnDelete();
            $table->string('challenge_theme');
            $table->unsignedSmallInteger('attempt_number');
            $table->unsignedTinyInteger('correct_answers');
            $table->unsignedTinyInteger('question_count');
            $table->unsignedTinyInteger('required_correct_answers');
            $table->boolean('passed');
            $table->timestamps();

            $table->index(['alarm_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarm_challenge_attempts');
    }
};
