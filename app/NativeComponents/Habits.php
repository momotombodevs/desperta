<?php

namespace App\NativeComponents;

use App\AlarmScheduling\AlarmOccurrenceReconciler;
use App\AlarmScheduling\ResumesActiveAlarm;
use App\Application\AlarmAnalytics\AlarmHabitsAnalytics;
use App\Application\AlarmAnalytics\WeeklyProgressImage;
use App\Application\Preferences\AppPreferences;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
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
        $preferences = app(AppPreferences::class);
        $preferences->applyLanguage();
        $this->weeklyGoalSelection = (string) $preferences->weeklyGoal();
    }

    public function navTitle(): string
    {
        return __('app.habits');
    }

    /**
     * @return array{
     *     current_streak: int,
     *     current_streak_label: string,
     *     best_streak: int,
     *     best_streak_label: string,
     *     on_time_count: int,
     *     resolved_count: int,
     *     on_time_rate: int,
     *     without_snooze_count: int,
     *     without_snooze_rate: int,
     *     days: list<array{date: string, label: string, status: string, on_time: int, late: int, missed: int, pending: int}>
     * }
     */
    #[Computed]
    public function habits(): array
    {
        $summary = app(AlarmHabitsAnalytics::class)->summarize();
        $language = app(AppPreferences::class)->language();
        $days = array_map(
            fn (array $day): array => [
                ...$day,
                'label' => Str::ucfirst(CarbonImmutable::parse($day['date'])->locale($language)->isoFormat('dd')),
            ],
            $summary['days'],
        );

        return [
            ...$summary,
            'days' => $days,
            'current_streak_label' => $summary['current_streak'].' '.($summary['current_streak'] === 1 ? __('app.day') : __('app.days')),
            'best_streak_label' => $summary['best_streak'].' '.($summary['best_streak'] === 1 ? __('app.day') : __('app.days')),
        ];
    }

    public function selectWeeklyGoal(string $goal): void
    {
        $selectedGoal = null;
        foreach (range(1, 7) as $days) {
            $localizedGoal = $days === 1
                ? __('app.weekly_goal_day', ['count' => $days])
                : __('app.weekly_goal_days', ['count' => $days]);

            if ($goal === (string) $days || $goal === $localizedGoal) {
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
        $language = app(AppPreferences::class)->language();
        $day = $weekday === null
            ? null
            : Str::ucfirst(CarbonImmutable::now((string) config('app.alarm_timezone'))->startOfWeek(CarbonInterface::MONDAY)->addDays($weekday - 1)->locale($language)->isoFormat('dddd'));

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
                    'label' => $day['label'],
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
