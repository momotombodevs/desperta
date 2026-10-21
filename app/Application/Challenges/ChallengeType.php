<?php

namespace App\Application\Challenges;

enum ChallengeType: string
{
    case Trivia = 'trivia';
    case Memory = 'memory';
    case Sequence = 'sequence';
    case MentalMath = 'mental_math';

    public function label(): string
    {
        return trans('challenges.types.'.$this->value.'.name');
    }

    /** @return list<self> */
    public static function playable(): array
    {
        return [self::Trivia, self::Memory, self::Sequence, self::MentalMath];
    }
}
