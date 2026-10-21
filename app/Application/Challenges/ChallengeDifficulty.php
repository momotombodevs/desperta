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
            self::Easy => 3,
            self::Normal => 4,
            self::Hard => 5,
        };
    }

    public function requiredCorrectAnswers(): int
    {
        return match ($this) {
            self::Easy => 2,
            self::Normal => 3,
            self::Hard => 4,
        };
    }

    /**
     * Trivia levels in the order they are drawn; later levels only fill in when earlier ones run out.
     *
     * @return list<string>
     */
    public function preferredQuestionLevels(): array
    {
        return match ($this) {
            self::Easy => ['easy', 'normal', 'hard'],
            self::Normal => ['normal', 'easy', 'hard'],
            self::Hard => ['hard', 'normal', 'easy'],
        };
    }

    public function memorizeSeconds(): int
    {
        return match ($this) {
            self::Easy => 10,
            self::Normal => 7,
            self::Hard => 5,
        };
    }
}
