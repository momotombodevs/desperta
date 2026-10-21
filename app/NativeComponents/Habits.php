<?php

namespace App\NativeComponents;

use App\AlarmScheduling\AlarmOccurrenceReconciler;
use App\AlarmScheduling\ResumesActiveAlarm;
use App\Application\AlarmAnalytics\AlarmHabitsAnalytics;
use App\Application\AlarmAnalytics\DayStatus;
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

    public string $weeklyGoalSelection = '4';

    /** Month shown in the calendar, as `Y-m`; empty means the current month. */
    public string $calendarMonth = '';

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
            $this->series(DayStatus::OnTime, __('app.on_time')),
            $this->series(DayStatus::Late, __('app.late')),
            $this->series(DayStatus::Missed, __('app.missed')),
        ];
    }

    /** @return list<array{id: string, label: string, value: int, color: string}> */
    #[Computed]
    public function outcomeSegments(): array
    {
        return [
            $this->segment(DayStatus::OnTime, __('app.on_time')),
            $this->segment(DayStatus::Late, __('app.late')),
            $this->segment(DayStatus::Missed, __('app.missed')),
        ];
    }

    /** Highest daily count in the bar chart, at least 2 so the axis shows whole numbers. */
    #[Computed]
    public function dailyChartMaximum(): int
    {
        return max(2, ...array_map(fn (array $day): int => max($day['on_time'], $day['late'], $day['missed']), $this->habits['days']));
    }

    public function showPreviousMonth(): void
    {
        $this->calendarMonth = $this->displayedMonth()->subMonthNoOverflow()->format('Y-m');
    }

    public function showNextMonth(): void
    {
        if ($this->isShowingCurrentMonth) {
            return;
        }

        $this->calendarMonth = $this->displayedMonth()->addMonthNoOverflow()->format('Y-m');
    }

    #[Computed]
    public function isShowingCurrentMonth(): bool
    {
        return $this->displayedMonth()->format('Y-m') === CarbonImmutable::now((string) config('app.alarm_timezone'))->format('Y-m');
    }

    #[Computed]
    public function calendarTitle(): string
    {
        return Str::ucfirst($this->displayedMonth()->locale(app(AppPreferences::class)->language())->isoFormat('MMMM YYYY'));
    }

    /** @return list<list<array{date: string, day: int, status: string, protected: bool, future: bool, in_month: bool}>> */
    #[Computed]
    public function calendarWeeks(): array
    {
        return array_chunk(app(AlarmHabitsAnalytics::class)->calendar($this->displayedMonth(), $this->habits['protected_days']), 7);
    }

    /** @return array<string, string> Weekday key => one-letter heading, Monday first. */
    #[Computed]
    public function weekdayInitials(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        return array_combine($days, array_map(fn (string $day): string => __("app.weekdays.{$day}.short"), $days));
    }

    /** @return array{started: int, completed: int, days: int} */
    #[Computed]
    public function routineCompletion(): array
    {
        return [...app(AlarmHabitsAnalytics::class)->routineCompletion(), 'days' => AlarmHabitsAnalytics::RoutineWindowDays];
    }

    /** @return array{samples: int, average_delay_minutes: int, variation_minutes: int}|null */
    #[Computed]
    public function wakeConsistency(): ?array
    {
        return app(AlarmHabitsAnalytics::class)->wakeConsistency();
    }

    /** @return array{nights: int, hours: int, minutes: int}|null Average real sleep from the bedtime reminder to actual wake-ups. */
    #[Computed]
    public function actualSleep(): ?array
    {
        $sleep = app(AlarmHabitsAnalytics::class)->actualSleep();

        return $sleep === null ? null : [
            'nights' => $sleep['nights'],
            'hours' => intdiv($sleep['average_minutes'], 60),
            'minutes' => $sleep['average_minutes'] % 60,
        ];
    }

    /** @return array{bedtime: string, alarm: string, hours: int, minutes: int}|null Sleep available before the next alarm. */
    #[Computed]
    public function plannedSleep(): ?array
    {
        $lifecycle = app(AlarmExecutionLifecycle::class);
        $nextAlarm = Alarm::query()
            ->where('enabled', true)
            ->get()
            ->sortBy(fn (Alarm $alarm): int => $lifecycle->nextScheduledFor($alarm)->getTimestamp())
            ->first();
        $minutes = $nextAlarm === null ? null : app(AlarmHabitsAnalytics::class)->plannedSleep($lifecycle->nextScheduledFor($nextAlarm));

        return $minutes === null ? null : [
            'bedtime' => Alarm::formatClock(app(BedtimeReminderSettings::class)->get()['time']),
            'alarm' => $nextAlarm->displayTime(),
            'hours' => intdiv($minutes, 60),
            'minutes' => $minutes % 60,
        ];
    }

    public function render(): View
    {
        return view('native.habits');
    }

    private function displayedMonth(): CarbonImmutable
    {
        $timezone = (string) config('app.alarm_timezone');

        return $this->calendarMonth === ''
            ? CarbonImmutable::now($timezone)->startOfMonth()
            : CarbonImmutable::createFromFormat('!Y-m', $this->calendarMonth, $timezone);
    }

    /** @return array{id: string, name: string, color: string, points: list<array{id: string, label: string, value: int}>} */
    private function series(DayStatus $status, string $name): array
    {
        return [
            'id' => $status->value,
            'name' => $name,
            'color' => theme($status->themeToken()),
            'points' => array_map(
                fn (array $day): array => [
                    'id' => "{$status->value}-{$day['date']}",
                    'label' => $day['label'],
                    'value' => $day[$status->value],
                ],
                $this->habits['days'],
            ),
        ];
    }

    /** @return array{id: string, label: string, value: int, color: string} */
    private function segment(DayStatus $status, string $label): array
    {
        return [
            'id' => $status->value,
            'label' => $label,
            'value' => array_sum(array_column($this->habits['days'], $status->value)),
            'color' => theme($status->themeToken()),
        ];
    }
}
