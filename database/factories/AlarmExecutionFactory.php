<?php

namespace Database\Factories;

use App\Models\Alarm;
use App\Models\AlarmExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlarmExecution>
 */
class AlarmExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alarm_id' => Alarm::factory(),
            'alarm_label' => 'Trabajo',
            'alarm_time' => '07:00',
            'status' => 'completed',
            'scheduled_for' => now(),
            'started_at' => now(),
            'finished_at' => now(),
            'snooze_count' => 0,
        ];
    }
}
