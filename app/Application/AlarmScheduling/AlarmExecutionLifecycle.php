<?php

namespace App\Application\AlarmScheduling;

use App\Models\Alarm;
use App\Models\AlarmExecution;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class AlarmExecutionLifecycle
{
    public function scheduleFor(Alarm $alarm): AlarmSchedule
    {
        $scheduledFor = $this->nextScheduledFor($alarm);

        return new AlarmSchedule(
            id: $alarm->id,
            time: $alarm->time,
            label: $alarm->label,
            weekdays: $alarm->weekdays,
            vibration: $alarm->vibration,
            snoozeEnabled: $alarm->snooze_enabled,
            difficulty: $alarm->difficulty,
            executionId: (string) Str::uuid(),
            scheduledFor: $scheduledFor->toIso8601String(),
        );
    }

    public function begin(string $alarmId, string $executionId, string $scheduledFor): ?AlarmExecution
    {
        $alarm = Alarm::query()->find($alarmId);

        if ($alarm === null) {
            return null;
        }

        AlarmExecution::query()
            ->where('alarm_id', $alarm->id)
            ->whereIn('status', ['ringing', 'snoozed'])
            ->where('id', '!=', $executionId)
            ->update(['status' => 'missed', 'finished_at' => now()]);

        $execution = AlarmExecution::query()->firstOrCreate(
            ['id' => $executionId],
            [
                'alarm_id' => $alarm->id,
                'alarm_label' => $alarm->label,
                'alarm_time' => $alarm->time,
                'status' => 'scheduled',
                'scheduled_for' => CarbonImmutable::parse($scheduledFor),
            ],
        );

        $execution->update(['status' => 'ringing', 'started_at' => now(), 'finished_at' => null]);

        return $execution;
    }

    public function snooze(AlarmExecution $execution): void
    {
        $execution->update([
            'status' => 'snoozed',
            'snoozed_at' => now(),
            'snooze_count' => $execution->snooze_count + 1,
        ]);
    }

    public function complete(AlarmExecution $execution): void
    {
        $execution->update(['status' => 'completed', 'finished_at' => now()]);
    }

    public function cancelOpen(Alarm $alarm): void
    {
        $alarm->executions()
            ->whereIn('status', ['scheduled', 'ringing', 'snoozed'])
            ->update(['status' => 'cancelled', 'finished_at' => now()]);
    }

    private function nextScheduledFor(Alarm $alarm): CarbonImmutable
    {
        $candidate = CarbonImmutable::now()->setTimeFromTimeString($alarm->time)->startOfMinute();

        for ($daysAhead = 0; $daysAhead <= 7; $daysAhead++) {
            if ($candidate->isFuture() && ($alarm->weekdays === [] || in_array($candidate->isoWeekday(), $alarm->weekdays, true))) {
                return $candidate;
            }

            $candidate = $candidate->addDay();
        }

        throw new \LogicException('Alarm weekdays must contain at least one valid day.');
    }
}
