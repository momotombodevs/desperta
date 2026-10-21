<?php

namespace Database\Factories;

use App\Models\DailyQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyQuestion>
 */
class DailyQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'challenge_theme' => 'nicaragua',
            'question' => [
                'id' => 'capital',
                'question' => '¿Cuál es la capital de Nicaragua?',
                'options' => ['León', 'Managua', 'Granada', 'Masaya'],
                'answer' => 'Managua',
                'explanation' => null,
            ],
            'selected_option' => null,
            'correct' => null,
            'answered_at' => null,
        ];
    }

    public function answered(bool $correct = true): static
    {
        return $this->state(fn (array $attributes): array => [
            'selected_option' => $correct ? 'Managua' : 'León',
            'correct' => $correct,
            'answered_at' => now(),
        ]);
    }
}
