<?php

namespace App\Application\Challenges;

use App\Icons\Android;

enum ChallengeTheme: string
{
    case Nicaragua = 'nicaragua';
    case Math = 'math';
    case GeneralKnowledge = 'general_knowledge';
    case WorldGeography = 'world_geography';
    case LatinAmerica = 'latin_america';
    case Football = 'football';
    case WizardingWorld = 'wizarding_world';
    case ScienceSpace = 'science_space';
    case Animals = 'animals';
    case History = 'history';
    case Languages = 'languages';
    case Mix = 'mix';

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

    /** @return list<self> */
    public static function packs(): array
    {
        return array_values(array_filter(self::cases(), fn (self $theme): bool => $theme !== self::Mix));
    }

    public function label(): string
    {
        return trans('challenges.'.$this->value.'.name');
    }

    public function icon(): Android
    {
        return match ($this) {
            self::Nicaragua => Android::Flag,
            self::Math => Android::Calculate,
            self::GeneralKnowledge => Android::Lightbulb,
            self::WorldGeography => Android::Public,
            self::LatinAmerica => Android::Map,
            self::Football => Android::SportsSoccer,
            self::WizardingWorld => Android::AutoFixHigh,
            self::ScienceSpace => Android::RocketLaunch,
            self::Animals => Android::Pets,
            self::History => Android::HistoryEdu,
            self::Languages => Android::Translate,
            self::Mix => Android::Shuffle,
        };
    }
}
