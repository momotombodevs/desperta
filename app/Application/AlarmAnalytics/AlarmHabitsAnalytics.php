<?php

namespace App\Application\AlarmAnalytics;

use App\Models\AlarmExecution;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class AlarmHabitsAnalytics
{
    private const int OnTimeMinutes = 10;

    /**
     * @return array{current_streak: int, best_streak: int, on_time_count: int, resolved_count: int, on_time_rate: int, without_snooze_count: int, without_snooze_rate: int, weekly_summary: array{on_time_mornings: int, resolved_count: int, on_time_count: int, late_count: int, missed_count: int, snooze_count: int, hardest_weekday: ?int, recommendation: string}, days: list<array{date: string, status: string, on_time: int, late: int, missed: int, pending: int}>}
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

    /** @return list<array{date: string, status: string, on_time: int, late: int, missed: int, pending: int}> */
    private function dayResults(Collection $executions, CarbonImmutable $start, CarbonImmutable $now): array
    {
        $days = collect(range(0, 6))->mapWithKeys(fn (int $offset): array => [
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

    /** @return array{current_streak: int, best_streak: int} */
    private function streaks(CarbonImmutable $now): array
    {
        $outcomes = AlarmExecution::query()
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_for')
            ->get()
            ->groupBy(fn (AlarmExecution $execution): string => $execution->scheduled_for->setTimezone($this->alarmTimezone())->toDateString())
            ->map(fn (Collection $executions): ?bool => $this->dayOutcome($executions, $now))
            ->filter(fn (?bool $outcome): bool => $outcome !== null)
            ->values();
        $best = 0;
        $running = 0;

        foreach ($outcomes as $outcome) {
            $running = $outcome ? $running + 1 : 0;
            $best = max($best, $running);
        }

        $current = 0;

        foreach ($outcomes->reverse() as $outcome) {
            if (! $outcome) {
                break;
            }

            $current++;
        }

        return ['current_streak' => $current, 'best_streak' => $best];
    }

    private function dayOutcome(Collection $executions, CarbonImmutable $now): ?bool
    {
        $statuses = $executions->map(fn (AlarmExecution $execution): string => $this->statusFor($execution, $now));

        if ($statuses->contains('pending')) {
            return null;
        }

        return $statuses->contains('on_time');
    }

    private function statusFor(AlarmExecution $execution, CarbonImmutable $now): string
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
