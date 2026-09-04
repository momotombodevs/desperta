<?php

namespace App\Application\Challenges;

enum ChallengeDifficulty: string
{
    case Easy = 'easy';
    case Normal = 'normal';
    case Hard = 'hard';

    public static function fromStored(string $difficulty): self
    {
        return match (trim($difficulty)) {
            'easy', 'Easy', 'Fácil', 'fácil' => self::Easy,
            'hard', 'Hard', 'Difícil', 'difícil' => self::Hard,
            default => self::Normal,
        };
    }

    public function questionCount(): int
    {
        return match ($this) {
            self::Hard => 5,
            default => 3,
        };
    }

    public function requiredCorrectAnswers(): int
    {
        return match ($this) {
            self::Easy => 2,
            self::Normal => 3,
            self::Hard => 5,
        };
    }
}
