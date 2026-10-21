<?php

namespace App\NativeComponents;

use App\AlarmScheduling\AlarmOccurrenceReconciler;
use App\AlarmScheduling\ResumesActiveAlarm;
use App\Application\AlarmAnalytics\AlarmHabitsAnalytics;
use App\Application\AlarmAnalytics\WeeklyProgressImage;
use App\Application\AlarmScheduling\AlarmExecutionLifecycle;
use App\Application\Preferences\AppPreferences;
use App\Application\Preferences\BedtimeReminderSettings;
use App\Models\Alarm;
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

    private const int MinimumSleepWindowMinutes = 180;

    private const int MaximumSleepWindowMinutes = 840;

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
     *     protected_days: list<string>,
     *     protector_available: bool,
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

    /** @return list<list<array{date: string, day: int, status: string, protected: bool, future: bool}>> */
    #[Computed]
    public function calendarWeeks(): array
    {
        return array_chunk(app(AlarmHabitsAnalytics::class)->calendar(), 7);
    }

    /** @return list<string> */
    public function weekdayInitials(): array
    {
        return array_map(
            fn (string $day): string => __("app.weekdays.{$day}.short"),
            ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        );
    }

    /** @return array{started: int, completed: int} */
    #[Computed]
    public function routineCompletion(): array
    {
        return app(AlarmHabitsAnalytics::class)->routineCompletion();
    }

    /** @return array{samples: int, average_delay_minutes: int, variation_minutes: int}|null */
    #[Computed]
    public function wakeConsistency(): ?array
    {
        return app(AlarmHabitsAnalytics::class)->wakeConsistency();
    }

    /**
     * Time between the bedtime reminder and the next alarm, shown only when it is a plausible night.
     *
     * @return array{bedtime: string, alarm: string, hours: int, minutes: int}|null
     */
    #[Computed]
    public function sleepWindow(): ?array
    {
        $bedtime = app(BedtimeReminderSettings::class)->get();
        $nextAlarm = Alarm::query()
            ->where('enabled', true)
            ->get()
            ->sortBy(fn (Alarm $alarm): int => app(AlarmExecutionLifecycle::class)->nextScheduledFor($alarm)->getTimestamp())
            ->first();

        if (! $bedtime['enabled'] || $nextAlarm === null) {
            return null;
        }

        $bedtimeMinutes = $this->minutesOfDay($bedtime['time']);
        $alarmMinutes = $this->minutesOfDay($nextAlarm->time);
        $window = ($alarmMinutes - $bedtimeMinutes + 1440) % 1440;

        if ($window < self::MinimumSleepWindowMinutes || $window > self::MaximumSleepWindowMinutes) {
            return null;
        }

        return [
            'bedtime' => $this->clockTime($bedtimeMinutes),
            'alarm' => $nextAlarm->displayTime(),
            'hours' => intdiv($window, 60),
            'minutes' => $window % 60,
        ];
    }

    public function render(): View
    {
        return view('native.habits');
    }

    private function minutesOfDay(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    private function clockTime(int $minutesOfDay): string
    {
        $hour = intdiv($minutesOfDay, 60);

        return sprintf('%d:%02d %s', $hour % 12 ?: 12, $minutesOfDay % 60, $hour < 12 ? __('app.time_am') : __('app.time_pm'));
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
