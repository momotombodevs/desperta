<?php

use Database\Seeders\HabitsDemoSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('app.habits_demo_data')) {
            return;
        }

        (new HabitsDemoSeeder)->run();
    }

    public function down(): void
    {
        (new HabitsDemoSeeder)->remove();
    }
};
