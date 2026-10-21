<?php

namespace App\Application\AlarmAnalytics;

use App\Application\MorningRoutine\MorningRoutineManager;
use App\Application\Preferences\BedtimeReminderSettings;
use App\Models\AlarmExecution;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class AlarmHabitsAnalytics
{
    private const int OnTimeMinutes = 10;

    public const int RoutineWindowDays = 7;

    private const int WakeWindowDays = 14;

    private const int MinimumSamples = 3;

    private const int ProtectorCooldownDays = 7;

    private const int MinimumSleepMinutes = 180;

    private const int MaximumSleepMinutes = 840;

    public function __construct(
        private readonly MorningRoutineManager $routines,
        private readonly BedtimeReminderSettings $bedtime,
    ) {}

    /**
     * @return array{current_streak: int, best_streak: int, protected_days: list<string>, protector_available: bool, on_time_count: int, resolved_count: int, on_time_rate: int, without_snooze_count: int, without_snooze_rate: int, weekly_summary: array{on_time_mornings: int, resolved_count: int, on_time_count: int, late_count: int, missed_count: int, snooze_count: int, hardest_weekday: ?int, recommendation: string}, days: list<array{date: string, status: string, on_time: int, late: int, missed: int, pending: int}>}
     */
    public function summarize(): array
    {
        $now = CarbonImmutable::now($this->alarmTimezone());
        $start = $now->startOfDay()->subDays(6);
        $end = $now->endOfDay();
        $executions = AlarmExecution::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_for', [$start->utc(), $end->utc()])
            ->get();
        $resolved = $executions->filter(fn (AlarmExecution $execution): bool => $this->statusFor($execution, $now) !== 'pending');
        $onTime = $executions->filter(fn (AlarmExecution $execution): bool => $this->statusFor($execution, $now) === 'on_time');
        $completed = $executions->where('status', 'completed');
        $withoutSnooze = $completed->where('snooze_count', 0);
        $weekStart = $now->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->addDays(6)->endOfDay();
        $weeklyExecutions = AlarmExecution::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_for', [$weekStart->utc(), $weekEnd->utc()])
            ->get();

        return [
            ...$this->streaks($now),
            'on_time_count' => $onTime->count(),
            'resolved_count' => $resolved->count(),
            'on_time_rate' => $resolved->isEmpty() ? 0 : (int) round(($onTime->count() / $resolved->count()) * 100),
            'without_snooze_count' => $withoutSnooze->count(),
            'without_snooze_rate' => $completed->isEmpty() ? 0 : (int) round(($withoutSnooze->count() / $completed->count()) * 100),
            'weekly_summary' => $this->weeklySummary($weeklyExecutions, $weekStart, $now),
            'days' => $this->dayResults($executions, $start, $now),
        ];
    }

    /**
     * @param  Collection<int, AlarmExecution>  $executions
     * @return array{on_time_mornings: int, resolved_count: int, on_time_count: int, late_count: int, missed_count: int, snooze_count: int, hardest_weekday: ?int, recommendation: string}
     */
    private function weeklySummary(Collection $executions, CarbonImmutable $weekStart, CarbonImmutable $now): array
    {
        $statuses = $executions->mapWithKeys(fn (AlarmExecution $execution): array => [
            $execution->id => $this->statusFor($execution, $now),
        ]);
        $resolved = $executions->filter(fn (AlarmExecution $execution): bool => $statuses[$execution->id] !== 'pending');
        $onTime = $executions->filter(fn (AlarmExecution $execution): bool => $statuses[$execution->id] === 'on_time'
            && $execution->snooze_count === 0);
        $difficultDays = AlarmExecution::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_for', [$weekStart->subDays(21)->utc(), $weekStart->subSecond()->utc()])
            ->orderBy('scheduled_for')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (AlarmExecution $execution): int => $execution->scheduled_for->setTimezone($this->alarmTimezone())->dayOfWeekIso)
            ->map(function (Collection $dayExecutions) use ($now): ?array {
                $resolvedOnDay = $dayExecutions->filter(fn (AlarmExecution $execution): bool => $this->statusFor($execution, $now) !== 'pending');
                if ($resolvedOnDay->count() < 2) {
                    return null;
                }

                $failed = $resolvedOnDay->filter(fn (AlarmExecution $execution): bool => $this->statusFor($execution, $now) !== 'on_time')->count();

                return ['failures' => $failed, 'count' => $resolvedOnDay->count()];
            })
            ->filter(fn (?array $day): bool => $day !== null)
            ->sortByDesc(fn (array $day): float => $day['failures'] / $day['count']);

        $hardestWeekday = $difficultDays->isNotEmpty() && $difficultDays->first()['failures'] > 0
            ? (int) $difficultDays->keys()->first()
            : null;
        $recommendation = $resolved->count() < 3
            ? 'habits_recommendation_more_data'
            : ($onTime->count() < $resolved->count() || $resolved->sum('snooze_count') > 0
                ? 'habits_recommendation_adjust'
                : 'habits_recommendation_keep');

        return [
            'on_time_mornings' => $onTime->map(fn (AlarmExecution $execution): string => $execution->scheduled_for->setTimezone($this->alarmTimezone())->toDateString())->unique()->count(),
            'resolved_count' => $resolved->count(),
            'on_time_count' => $onTime->count(),
            'late_count' => $statuses->filter(fn (string $status): bool => $status === 'late')->count(),
            'missed_count' => $statuses->filter(fn (string $status): bool => $status === 'missed')->count(),
            'snooze_count' => $executions->sum('snooze_count'),
            'hardest_weekday' => $hardestWeekday,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Day-by-day results for one month, padded to whole Monday-first weeks so the
     * screen can lay them out as a calendar grid.
     *
     * @param  list<string>|null  $protectedDays  Pass the summary's protected days to avoid recomputing streaks.
     * @return list<array{date: string, day: int, status: string, protected: bool, future: bool, in_month: bool}>
     */
    public function calendar(CarbonImmutable $month, ?array $protectedDays = null): array
    {
        $now = CarbonImmutable::now($this->alarmTimezone());
        $month = $month->setTimezone($this->alarmTimezone());
        $start = $month->startOfMonth()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $end = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY)->endOfDay();
        $executions = AlarmExecution::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_for', [$start->utc(), $end->utc()])
            ->get();
        $protectedDays ??= $this->streaks($now)['protected_days'];

        return array_map(fn (array $day): array => [
            'date' => $day['date'],
            'day' => (int) CarbonImmutable::parse($day['date'])->day,
            'status' => $day['status'],
            'protected' => in_array($day['date'], $protectedDays, true),
            'future' => $day['date'] > $now->toDateString(),
            'in_month' => str_starts_with($day['date'], $month->format('Y-m')),
        ], $this->dayResults($executions, $start, $now, (int) $start->diffInDays($end) + 1));
    }

    /** @return array{started: int, completed: int} Morning routines begun and fully finished in the recent window. */
    public function routineCompletion(): array
    {
        $now = CarbonImmutable::now($this->alarmTimezone());
        $outcomes = $this->routines->routineOutcomes($now->startOfDay()->subDays(self::RoutineWindowDays - 1), $now->endOfDay());

        return [
            'started' => $outcomes->count(),
            'completed' => $outcomes->filter()->count(),
        ];
    }

    public function bestStreak(): int
    {
        return $this->streaks(CarbonImmutable::now($this->alarmTimezone()))['best_streak'];
    }

    /** Longest run of consecutive alarm days where every alarm was turned off without snoozing. */
    public function bestNoSnoozeRun(): int
    {
        $best = 0;
        $running = 0;

        $days = AlarmExecution::query()
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_for')
            ->get()
            ->groupBy(fn (AlarmExecution $execution): string => $execution->scheduled_for->setTimezone($this->alarmTimezone())->toDateString());

        foreach ($days as $executions) {
            if ($executions->contains(fn (AlarmExecution $execution): bool => in_array($execution->status, ['ringing', 'scheduled', 'snoozed'], true))) {
                continue;
            }

            $running = $executions->every(fn (AlarmExecution $execution): bool => $execution->status === 'completed' && $execution->snooze_count === 0)
                ? $running + 1
                : 0;
            $best = max($best, $running);
        }

        return $best;
    }

    /**
     * How long it takes to turn alarms off after they ring, over the last two weeks.
     * Measured relative to each alarm so mornings with different alarm times compare fairly.
     *
     * @return array{samples: int, average_delay_minutes: int, variation_minutes: int}|null Null until there are three mornings.
     */
    public function wakeConsistency(): ?array
    {
        $now = CarbonImmutable::now($this->alarmTimezone());
        $delays = AlarmExecution::query()
            ->where('status', 'completed')
            ->whereNotNull('finished_at')
            ->whereBetween('scheduled_for', [$now->startOfDay()->subDays(self::WakeWindowDays - 1)->utc(), $now->endOfDay()->utc()])
            ->get()
            ->map(fn (AlarmExecution $execution): float => max(0, $execution->scheduled_for->diffInSeconds($execution->finished_at, false)) / 60);

        if ($delays->count() < self::MinimumSamples) {
            return null;
        }

        $average = $delays->avg();
        $variance = $delays->map(fn (float $delay): float => ($delay - $average) ** 2)->avg();

        return [
            'samples' => $delays->count(),
            'average_delay_minutes' => (int) round($average),
            'variation_minutes' => (int) round(sqrt($variance)),
        ];
    }

    /**
     * Average real sleep: from the bedtime reminder of the night before to each actual
     * wake-up over the last two weeks. Nights outside a plausible range are ignored.
     *
     * @return array{nights: int, average_minutes: int}|null Null until the reminder is on and there are enough nights.
     */
    public function actualSleep(): ?array
    {
        $bedtime = $this->bedtime->get();

        if (! $bedtime['enabled']) {
            return null;
        }

        $now = CarbonImmutable::now($this->alarmTimezone());
        $nights = AlarmExecution::query()
            ->where('status', 'completed')
            ->whereNotNull('finished_at')
            ->whereBetween('scheduled_for', [$now->startOfDay()->subDays(self::WakeWindowDays - 1)->utc(), $now->endOfDay()->utc()])
            ->get()
            ->map(fn (AlarmExecution $execution): ?int => $this->minutesSinceBedtime($execution->finished_at, $bedtime))
            ->filter(fn (?int $minutes): bool => $minutes !== null);

        if ($nights->count() < self::MinimumSamples) {
            return null;
        }

        return ['nights' => $nights->count(), 'average_minutes' => (int) round($nights->avg())];
    }

    /** Minutes between the bedtime reminder and the given alarm, when it falls on a reminder night and is plausible. */
    public function plannedSleep(CarbonImmutable $alarmAt): ?int
    {
        $bedtime = $this->bedtime->get();

        return $bedtime['enabled'] ? $this->minutesSinceBedtime($alarmAt, $bedtime) : null;
    }

    /** @param array{enabled: bool, time: string, weekdays: list<int>} $bedtime */
    private function minutesSinceBedtime(CarbonImmutable $wakeAt, array $bedtime): ?int
    {
        $wakeAt = $wakeAt->setTimezone($this->alarmTimezone());
        $wentToBed = $wakeAt->setTimeFromTimeString($bedtime['time']);

        if ($wentToBed->greaterThanOrEqualTo($wakeAt)) {
            $wentToBed = $wentToBed->subDay();
        }

        if (! in_array($wentToBed->dayOfWeekIso, $bedtime['weekdays'], true)) {
            return null;
        }

        $minutes = (int) $wentToBed->diffInMinutes($wakeAt);

        return $minutes >= self::MinimumSleepMinutes && $minutes <= self::MaximumSleepMinutes ? $minutes : null;
    }

    /** @return list<array{date: string, status: string, on_time: int, late: int, missed: int, pending: int}> */
    private function dayResults(Collection $executions, CarbonImmutable $start, CarbonImmutable $now, int $dayCount = 7): array
    {
        $days = collect(range(0, $dayCount - 1))->mapWithKeys(fn (int $offset): array => [
            $start->addDays($offset)->toDateString() => [
                'date' => $start->addDays($offset)->toDateString(),
                'status' => 'none',
                'on_time' => 0,
                'late' => 0,
                'missed' => 0,
                'pending' => 0,
            ],
        ])->all();

        foreach ($executions as $execution) {
            $date = $execution->scheduled_for->setTimezone($this->alarmTimezone())->toDateString();

            if (! isset($days[$date])) {
                continue;
            }

            $days[$date][$this->statusFor($execution, $now)]++;
        }

        return array_values(array_map(function (array $day): array {
            $day['status'] = $day['missed'] > 0 ? 'missed' : ($day['late'] > 0 ? 'late' : ($day['pending'] > 0 ? 'pending' : ($day['on_time'] > 0 ? 'on_time' : 'none')));

            return $day;
        }, $days));
    }

    /**
     * Counts consecutive on-time days. A streak protector forgives one failed day and
     * recharges seven days later, so a single bad morning does not wipe out the run.
     *
     * @return array{current_streak: int, best_streak: int, protected_days: list<string>, protector_available: bool}
     */
    private function streaks(CarbonImmutable $now): array
    {
        $outcomes = AlarmExecution::query()
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_for')
            ->get()
            ->groupBy(fn (AlarmExecution $execution): string => $execution->scheduled_for->setTimezone($this->alarmTimezone())->toDateString())
            ->map(fn (Collection $executions): ?bool => $this->dayOutcome($executions, $now))
            ->filter(fn (?bool $outcome): bool => $outcome !== null);
        $best = 0;
        $running = 0;
        $protectedDays = [];

        foreach ($outcomes as $date => $outcome) {
            if ($outcome) {
                $running++;
            } elseif ($running > 0 && $this->protectorReady($protectedDays, (string) $date)) {
                $protectedDays[] = (string) $date;
            } else {
                $running = 0;
            }

            $best = max($best, $running);
        }

        return [
            'current_streak' => $running,
            'best_streak' => $best,
            'protected_days' => $protectedDays,
            'protector_available' => $this->protectorReady($protectedDays, $now->setTimezone($this->alarmTimezone())->toDateString()),
        ];
    }

    /** @param list<string> $protectedDays */
    private function protectorReady(array $protectedDays, string $date): bool
    {
        $lastUsed = end($protectedDays);

        return $lastUsed === false
            || CarbonImmutable::parse($lastUsed)->diffInDays(CarbonImmutable::parse($date)) >= self::ProtectorCooldownDays;
    }

    private function dayOutcome(Collection $executions, CarbonImmutable $now): ?bool
    {
        $statuses = $executions->map(fn (AlarmExecution $execution): string => $this->statusFor($execution, $now));

        if ($statuses->contains('pending')) {
            return null;
        }

        return $statuses->contains('on_time');
    }

    /** Classifies an execution as on_time, late, missed or pending relative to now. */
    public function statusFor(AlarmExecution $execution, CarbonImmutable $now): string
    {
        if ($execution->status === 'completed' && $execution->finished_at !== null) {
            return $execution->finished_at->lessThanOrEqualTo($execution->scheduled_for->addMinutes(self::OnTimeMinutes)) ? 'on_time' : 'late';
        }

        if ($execution->status === 'missed' || $execution->scheduled_for->addMinutes(self::OnTimeMinutes)->lessThanOrEqualTo($now)) {
            return 'missed';
        }

        return 'pending';
    }

    private function alarmTimezone(): string
    {
        return (string) config('app.alarm_timezone');
    }
}
