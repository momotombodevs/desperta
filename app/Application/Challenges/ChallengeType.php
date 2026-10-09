<?php

namespace App\Application\Challenges;

enum ChallengeType: string
{
    case Trivia = 'trivia';
    case Memory = 'memory';
    case Sequence = 'sequence';
    case MentalMath = 'mental_math';

    /** @return list<self> */
    public static function playable(): array
    {
        return [self::Trivia, self::Memory, self::Sequence, self::MentalMath];
    }
}
