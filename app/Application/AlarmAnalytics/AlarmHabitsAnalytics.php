<?php

namespace App\Application\AlarmAnalytics;

use App\Models\AlarmExecution;
use Carbon\CarbonImmutable;

final class AlarmHabitsAnalytics
{
    private const string Timezone = 'America/Managua';

    /**
     * @return array{
     *     completed: int,
     *     missed: int,
     *     total: int,
     *     completion_rate: int,
     *     days: list<array{date: string, completed: int, missed: int}>
     * }
     */
    public function summarize(): array
    {
        $today = CarbonImmutable::now(self::Timezone)->startOfDay();
        $start = $today->subDays(6);
        $end = $today->endOfDay();
        $days = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $date = $start->addDays($offset)->toDateString();

            $days[$date] = [
                'date' => $date,
                'completed' => 0,
                'missed' => 0,
            ];
        }

        $executions = AlarmExecution::query()
            ->select(['status', 'scheduled_for'])
            ->whereIn('status', ['completed', 'missed'])
            ->whereBetween('scheduled_for', [$start->utc(), $end->utc()])
            ->get();

        foreach ($executions as $execution) {
            $date = $execution->scheduled_for->setTimezone(self::Timezone)->toDateString();

            if (! isset($days[$date])) {
                continue;
            }

            $days[$date][$execution->status]++;
        }

        $completed = array_sum(array_column($days, 'completed'));
        $missed = array_sum(array_column($days, 'missed'));
        $total = $completed + $missed;

        return [
            'completed' => $completed,
            'missed' => $missed,
            'total' => $total,
            'completion_rate' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
            'days' => array_values($days),
        ];
    }
}
