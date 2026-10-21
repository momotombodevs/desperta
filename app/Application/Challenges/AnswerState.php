<?php

namespace App\Application\Challenges;

/** How an answer option looks before and after the question is graded. */
enum AnswerState: string
{
    case Idle = 'idle';
    case Selected = 'selected';
    case Correct = 'correct';
    case Wrong = 'wrong';
    case Dimmed = 'dimmed';

    public static function for(bool $revealed, bool $isAnswer, bool $isSelected): self
    {
        if (! $revealed) {
            return $isSelected ? self::Selected : self::Idle;
        }

        return match (true) {
            $isAnswer => self::Correct,
            $isSelected => self::Wrong,
            default => self::Dimmed,
        };
    }

    public function containerClasses(): string
    {
        return match ($this) {
            self::Correct => 'border-theme-success bg-theme-success/15',
            self::Wrong => 'border-theme-destructive bg-theme-destructive/15',
            self::Selected => 'border-theme-primary bg-theme-primary/15',
            self::Idle, self::Dimmed => 'border-theme-outline bg-theme-surface',
        };
    }

    public function opacity(): float
    {
        return $this === self::Dimmed ? 0.45 : 1.0;
    }

    public function scale(): float
    {
        return $this === self::Correct ? 1.03 : 1.0;
    }

    public function isPressable(): bool
    {
        return $this === self::Idle || $this === self::Selected;
    }
}
