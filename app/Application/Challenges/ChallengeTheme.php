<?php

namespace App\Application\Challenges;

enum ChallengeTheme: string
{
    case Nicaragua = 'nicaragua';
    case Math = 'math';
    case GeneralKnowledge = 'general_knowledge';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return array_combine(
            self::values(),
            array_map(fn (self $theme): string => $theme->label(), self::cases()),
        );
    }

    public function label(): string
    {
        return trans('challenges.'.$this->value.'.name');
    }
}
