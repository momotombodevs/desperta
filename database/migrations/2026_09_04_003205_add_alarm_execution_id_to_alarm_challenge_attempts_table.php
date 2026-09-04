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
        Schema::table('alarm_challenge_attempts', function (Blueprint $table): void {
            $table->foreignUuid('alarm_execution_id')
                ->nullable()
                ->after('alarm_id')
                ->constrained('alarm_executions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alarm_challenge_attempts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('alarm_execution_id');
        });
    }
};
