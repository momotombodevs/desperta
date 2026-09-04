<?php

namespace App\Application\AlarmScheduling;

use App\AlarmScheduling\NativeAlarmOccurrenceEvent;
use App\Models\Alarm;
use App\Models\AlarmExecution;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class AlarmExecutionLifecycle
{
    public function scheduleFor(Alarm $alarm): AlarmSchedule
    {
        $scheduledFor = $this->nextScheduledFor($alarm);
        $executionId = (string) Str::uuid();

        AlarmExecution::query()->create([
            'id' => $executionId,
            'alarm_id' => $alarm->id,
            'alarm_label' => $alarm->label,
            'alarm_time' => $alarm->time,
            'status' => 'scheduled',
            'scheduled_for' => $scheduledFor,
        ]);

        return new AlarmSchedule(
            id: $alarm->id,
            time: $alarm->time,
            label: $alarm->label,
            weekdays: $alarm->weekdays,
            vibration: $alarm->vibration,
            snoozeEnabled: $alarm->snooze_enabled,
            difficulty: $alarm->challengeDifficulty()->value,
            executionId: $executionId,
            scheduledFor: $scheduledFor->toIso8601String(),
            snoozeMinutes: $alarm->snoozeMinutes(),
            notificationTitle: filled($alarm->label) ? $alarm->label : __('app.alarm_notification_title'),
            notificationBody: __('app.alarm_notification_body'),
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

    public function reconcile(NativeAlarmOccurrenceEvent $event): bool
    {
        $alarm = Alarm::query()->find($event->alarmId);

        if ($alarm === null || ! in_array($event->status, ['scheduled', 'triggered', 'snoozed', 'completed', 'cancelled', 'missed'], true)) {
            return false;
        }

        $occurredAt = CarbonImmutable::parse($event->occurredAt);
        $scheduledFor = $this->normalizedScheduledFor($event, $alarm, $occurredAt);

        $execution = AlarmExecution::query()->firstOrCreate(
            ['id' => $event->executionId],
            [
                'alarm_id' => $alarm->id,
                'alarm_label' => $alarm->label,
                'alarm_time' => $alarm->time,
                'status' => 'scheduled',
                'scheduled_for' => $scheduledFor,
            ],
        );

        if (in_array($execution->status, ['completed', 'cancelled', 'missed'], true) && ! in_array($event->status, ['completed', 'cancelled', 'missed'], true)) {
            return true;
        }

        $updates = match ($event->status) {
            'triggered' => ['status' => 'ringing', 'started_at' => $occurredAt, 'finished_at' => null],
            'snoozed' => ['status' => 'snoozed', 'snoozed_at' => $occurredAt, 'snooze_count' => max($execution->snooze_count, $event->snoozeCount)],
            'completed', 'cancelled', 'missed' => ['status' => $event->status, 'finished_at' => $occurredAt],
            default => ['status' => 'scheduled'],
        };

        $execution->update(['scheduled_for' => $scheduledFor, ...$updates]);

        return true;
    }

    public function nextScheduledFor(Alarm $alarm): CarbonImmutable
    {
        $candidate = CarbonImmutable::now($this->alarmTimezone())->setTimeFromTimeString($alarm->time)->startOfMinute();

        for ($daysAhead = 0; $daysAhead <= 7; $daysAhead++) {
            if ($candidate->isFuture() && ($alarm->weekdays === [] || in_array($candidate->isoWeekday(), $alarm->weekdays, true))) {
                return $candidate->utc();
            }

            $candidate = $candidate->addDay();
        }

        throw new \LogicException('Alarm weekdays must contain at least one valid day.');
    }

    private function normalizedScheduledFor(NativeAlarmOccurrenceEvent $event, Alarm $alarm, CarbonImmutable $occurredAt): CarbonImmutable
    {
        $scheduledFor = CarbonImmutable::parse($event->scheduledFor)->utc();

        if (! in_array($event->status, ['triggered', 'snoozed', 'completed', 'missed'], true) || $scheduledFor->lessThanOrEqualTo($occurredAt)) {
            return $scheduledFor;
        }

        $localScheduledFor = $occurredAt
            ->setTimezone($this->alarmTimezone())
            ->setTimeFromTimeString($alarm->time)
            ->startOfMinute();

        if ($localScheduledFor->isAfter($occurredAt)) {
            $localScheduledFor = $localScheduledFor->subDay();
        }

        return $localScheduledFor->utc();
    }

    private function alarmTimezone(): string
    {
        return (string) config('app.alarm_timezone');
    }
}
