<?php

namespace App\NativeComponents;

use App\AlarmScheduling\AlarmOccurrenceReconciler;
use App\AlarmScheduling\ResumesActiveAlarm;
use App\Application\AlarmAnalytics\AlarmHabitsAnalytics;
use App\Application\AlarmAnalytics\WeeklyProgressImage;
use App\Application\Preferences\AppPreferences;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Share;

final class Habits extends NativeComponent
{
    use ResumesActiveAlarm;

    public string $weeklyGoalSelection = '4';

    public function mount(): void
    {
        app(AlarmOccurrenceReconciler::class)->reconcile();
        $this->weeklyGoalSelection = (string) app(AppPreferences::class)->weeklyGoal();
    }

    public function navTitle(): string
    {
        return __('app.habits');
    }

    /**
     * @return array{
     *     current_streak: int,
     *     best_streak: int,
     *     on_time_count: int,
     *     resolved_count: int,
     *     on_time_rate: int,
     *     without_snooze_count: int,
     *     without_snooze_rate: int,
     *     days: list<array{date: string, status: string, on_time: int, late: int, missed: int, pending: int}>
     * }
     */
    #[Computed]
    public function habits(): array
    {
        return app(AlarmHabitsAnalytics::class)->summarize();
    }

    public function selectWeeklyGoal(string $goal): void
    {
        $selectedGoal = null;
        foreach (range(1, 7) as $days) {
            if ($goal === (string) $days || $goal === __('app.weekly_goal_days', ['count' => $days])) {
                $selectedGoal = $days;
                break;
            }
        }

        if ($selectedGoal === null) {
            return;
        }

        $this->weeklyGoalSelection = (string) $selectedGoal;
        app(AppPreferences::class)->setWeeklyGoal($selectedGoal);
    }

    public function shareWeeklyProgress(): void
    {
        $summary = $this->habits['weekly_summary'];
        $filePath = app(WeeklyProgressImage::class)->create(
            $summary,
            (int) $this->weeklyGoalSelection,
            $this->habits['current_streak'],
            $this->habits['best_streak'],
        );

        Share::file(__('app.weekly_share_title'), __('app.weekly_share_message'), $filePath);
    }

    /** @return array{goal: int, completed: int, recommendation: string, hardest_day: ?string, on_time_rate: int} */
    #[Computed]
    public function weeklyProgress(): array
    {
        $summary = $this->habits['weekly_summary'];
        $weekday = $summary['hardest_weekday'];
        $day = $weekday === null
            ? null
            : CarbonImmutable::now((string) config('app.alarm_timezone'))->startOfWeek(CarbonInterface::MONDAY)->addDays($weekday - 1)->locale(app()->getLocale())->isoFormat('dddd');

        return [
            'goal' => (int) $this->weeklyGoalSelection,
            'completed' => $summary['on_time_mornings'],
            'recommendation' => $summary['recommendation'],
            'hardest_day' => $day,
            'on_time_rate' => $summary['resolved_count'] === 0 ? 0 : (int) round(($summary['on_time_count'] / $summary['resolved_count']) * 100),
        ];
    }

    /** @return list<array{id: string, name: string, color: string, points: list<array{id: string, label: string, value: int}>}> */
    #[Computed]
    public function dailySeries(): array
    {
        return [
            $this->series('on_time', __('app.on_time'), theme('success')),
            $this->series('late', __('app.late'), theme('warning')),
            $this->series('missed', __('app.missed'), theme('destructive')),
        ];
    }

    /** @return list<array{id: string, label: string, value: int, color: string}> */
    #[Computed]
    public function outcomeSegments(): array
    {
        return [
            $this->segment('on_time', __('app.on_time'), theme('success')),
            $this->segment('late', __('app.late'), theme('warning')),
            $this->segment('missed', __('app.missed'), theme('destructive')),
        ];
    }

    public function render(): View
    {
        return view('native.habits');
    }

    /** @return array{id: string, name: string, color: string, points: list<array{id: string, label: string, value: int}>} */
    private function series(string $status, string $name, string $color): array
    {
        return [
            'id' => $status,
            'name' => $name,
            'color' => $color,
            'points' => array_map(
                fn (array $day): array => [
                    'id' => "{$status}-{$day['date']}",
                    'label' => CarbonImmutable::parse($day['date'])->locale(app()->getLocale())->isoFormat('dd'),
                    'value' => $day[$status],
                ],
                $this->habits['days'],
            ),
        ];
    }

    /** @return array{id: string, label: string, value: int, color: string} */
    private function segment(string $status, string $label, string $color): array
    {
        return [
            'id' => $status,
            'label' => $label,
            'value' => array_sum(array_column($this->habits['days'], $status)),
            'color' => $color,
        ];
    }
}
