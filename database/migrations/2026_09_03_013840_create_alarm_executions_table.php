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
        Schema::create('alarm_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('alarm_id')->nullable()->constrained()->nullOnDelete();
            $table->string('alarm_label');
            $table->string('alarm_time', 5);
            $table->string('status');
            $table->dateTime('scheduled_for');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('snoozed_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->unsignedTinyInteger('snooze_count')->default(0);
            $table->timestamps();

            $table->index(['alarm_id', 'status']);
            $table->index(['scheduled_for', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarm_executions');
    }
};
