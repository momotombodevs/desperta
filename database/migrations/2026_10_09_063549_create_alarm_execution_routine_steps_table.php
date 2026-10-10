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
        Schema::create('alarm_execution_routine_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('alarm_execution_id')->constrained('alarm_executions')->cascadeOnDelete();
            $table->foreignUuid('morning_routine_step_id')->nullable()->constrained('morning_routine_steps')->nullOnDelete();
            $table->string('label', 80);
            $table->unsignedSmallInteger('position');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['alarm_execution_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarm_execution_routine_steps');
    }
};
