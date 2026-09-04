<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('alarms', function (Blueprint $table) {
            $table->unsignedTinyInteger('snooze_minutes')->default(5);
        });

        DB::table('alarms')->whereIn('difficulty', ['easy', 'Easy', 'Fácil', 'fácil'])->update(['difficulty' => 'easy']);
        DB::table('alarms')->whereIn('difficulty', ['hard', 'Hard', 'Difícil', 'difícil'])->update(['difficulty' => 'hard']);
        DB::table('alarms')->whereIn('difficulty', ['normal', 'Normal'])->update(['difficulty' => 'normal']);
        DB::table('alarms')->whereNotIn('difficulty', ['easy', 'normal', 'hard'])->update(['difficulty' => 'normal']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alarms', function (Blueprint $table) {
            $table->dropColumn('snooze_minutes');
        });
    }
};
