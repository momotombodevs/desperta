<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alarms', function (Blueprint $table) {
            $table->string('scheduling_status')->default('pending');
        });

        DB::table('alarms')->where('enabled', false)->update(['scheduling_status' => 'not_scheduled']);
    }

    public function down(): void
    {
        Schema::table('alarms', function (Blueprint $table) {
            $table->dropColumn('scheduling_status');
        });
    }
};
