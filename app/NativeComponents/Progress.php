<?php

namespace App\NativeComponents;

use App\AlarmScheduling\ResumesActiveAlarm;
use App\Application\PlayerProgress\Achievement;
use App\Application\PlayerProgress\PlayerProgress;
use App\Application\Preferences\AppPreferences;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

final class Progress extends NativeComponent
{
    use ResumesActiveAlarm;

    public function mount(): void
    {
        app(AppPreferences::class)->applyLanguage();
    }

    /** @return array{xp: int, level: int, title: string, level_floor: int, next_level_xp: ?int, progress: float} */
    #[Computed]
    public function summary(): array
    {
        return app(PlayerProgress::class)->summary();
    }

    /** @return list<array{achievement: Achievement, progress: int, goal: int, unlocked: bool}> */
    #[Computed]
    public function achievements(): array
    {
        return app(PlayerProgress::class)->achievements();
    }

    /** @return array<string, int> */
    public function xpRules(): array
    {
        return [
            'on_time' => PlayerProgress::OnTimeXp,
            'late' => PlayerProgress::LateXp,
            'no_snooze' => PlayerProgress::NoSnoozeXp,
            'perfect' => PlayerProgress::PerfectChallengeXp,
            'routine' => PlayerProgress::RoutineXp,
            'daily_question' => PlayerProgress::DailyQuestionXp,
        ];
    }

    public function render(): View
    {
        return view('native.progress');
    }
}
