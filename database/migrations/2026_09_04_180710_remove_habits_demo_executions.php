<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('alarm_executions')
            ->whereNull('alarm_id')
            ->where('alarm_label', '[DEMO] Historial de hábitos')
            ->delete();
    }

    public function down(): void {}
};
