<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alarms', function (Blueprint $table): void {
            $table->date('alarm_date')->nullable()->after('weekdays');
        });
    }

    public function down(): void
    {
        Schema::table('alarms', function (Blueprint $table): void {
            $table->dropColumn('alarm_date');
        });
    }
};
