<?php

namespace App\Application\PlayerProgress;

use App\Application\Challenges\ChallengeTheme;
use App\Icons\Android;

enum Achievement: string
{
    case FirstWake = 'first_wake';
    case Streak3 = 'streak_3';
    case Streak7 = 'streak_7';
    case Streak30 = 'streak_30';
    case NoSnoozeWeek = 'no_snooze_week';
    case Lightning = 'lightning';
    case Perfect10 = 'perfect_10';
    case Combo5 = 'combo_5';
    case Explorer5 = 'explorer_5';
    case AllTopics = 'all_topics';
    case Routine5 = 'routine_5';
    case DailyQuestion7 = 'daily_question_7';

    public function goal(): int
    {
        return match ($this) {
            self::FirstWake, self::Lightning => 1,
            self::Streak3 => 3,
            self::Streak7, self::NoSnoozeWeek, self::DailyQuestion7 => 7,
            self::Streak30 => 30,
            self::Perfect10 => 10,
            self::Combo5, self::Explorer5, self::Routine5 => 5,
            self::AllTopics => count(ChallengeTheme::packs()),
        };
    }

    public function icon(): Android
    {
        return match ($this) {
            self::FirstWake => Android::WbSunny,
            self::Streak3, self::Streak7 => Android::LocalFireDepartment,
            self::Streak30 => Android::Whatshot,
            self::NoSnoozeWeek => Android::AlarmOn,
            self::Lightning => Android::Bolt,
            self::Perfect10 => Android::Star,
            self::Combo5 => Android::AutoAwesome,
            self::Explorer5 => Android::Explore,
            self::AllTopics => Android::Public,
            self::Routine5 => Android::SelfImprovement,
            self::DailyQuestion7 => Android::Quiz,
        };
    }

    public function title(): string
    {
        return trans('progress.achievements.'.$this->value.'.title');
    }

    public function description(): string
    {
        return trans('progress.achievements.'.$this->value.'.description', ['goal' => $this->goal()]);
    }
}
