<?php

namespace Database\Factories;

use App\Models\Alarm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alarm>
 */
class AlarmFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'time' => '07:00',
            'label' => fake()->words(2, true),
            'weekdays' => [1, 2, 3, 4, 5],
            'vibration' => true,
            'snooze_enabled' => true,
            'difficulty' => 'Normal',
            'enabled' => true,
            'scheduling_status' => 'scheduled',
        ];
    }
}
