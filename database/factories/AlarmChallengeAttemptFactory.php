<?php

namespace Database\Factories;

use App\Models\Alarm;
use App\Models\AlarmChallengeAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlarmChallengeAttempt>
 */
class AlarmChallengeAttemptFactory extends Factory
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
            'challenge_theme' => 'nicaragua',
            'attempt_number' => 1,
            'correct_answers' => 2,
            'question_count' => 3,
            'required_correct_answers' => 2,
            'passed' => true,
        ];
    }
}
