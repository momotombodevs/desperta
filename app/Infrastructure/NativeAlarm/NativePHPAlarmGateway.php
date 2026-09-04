<?php

namespace App\Infrastructure\NativeAlarm;

use App\AlarmScheduling\ActiveAlarmOccurrence;
use App\AlarmScheduling\NativeAlarmOccurrenceEvent;
use App\Application\AlarmScheduling\AlarmSchedule;
use App\Application\AlarmScheduling\NativeAlarmGateway;
use Momotombo\NativePHPAlarms\AlarmScheduler;
use Momotombo\NativePHPAlarms\DTO\AlarmConfiguration;
use Momotombo\NativePHPAlarms\Enums\Weekday;

final class NativePHPAlarmGateway implements NativeAlarmGateway
{
    public function __construct(private AlarmScheduler $alarms) {}

    public function canScheduleExactly(): bool
    {
        return $this->alarms->canSchedule();
    }

    public function requestExactAlarmPermission(): void
    {
        $this->alarms->requestAuthorization();
    }

    public function canUseFullScreenIntent(): bool
    {
        return $this->alarms->canUseFullScreenIntent();
    }

    public function requestFullScreenIntentPermission(): void
    {
        $this->alarms->requestFullScreenIntentAuthorization();
    }

    public function canPostNotifications(): bool
    {
        return $this->alarms->canPostNotifications();
    }

    public function requestNotificationPermission(string $requestId): void
    {
        $this->alarms->requestNotificationAuthorization($requestId);
    }

    public function activeRingingOccurrence(): ?ActiveAlarmOccurrence
    {
        if (! function_exists('nativephp_call')) {
            return null;
        }

        $occurrence = $this->alarms->activeRingingOccurrence();

        if ($occurrence === null) {
            return null;
        }

        return new ActiveAlarmOccurrence(
            alarmId: $occurrence->alarmId,
            executionId: $occurrence->executionId,
            scheduledFor: $occurrence->scheduledFor,
        );
    }

    /** @return list<NativeAlarmOccurrenceEvent> */
    public function occurrenceEvents(): array
    {
        if (! function_exists('nativephp_call')) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $event): ?NativeAlarmOccurrenceEvent => $this->occurrenceEvent($event),
            $this->alarms->occurrenceEvents(),
        )));
    }

    /** @param list<string> $executionIds */
    public function acknowledgeOccurrenceEvents(array $executionIds): void
    {
        if (function_exists('nativephp_call') && $executionIds !== []) {
            $this->alarms->acknowledgeOccurrenceEvents($executionIds);
        }
    }

    public function schedule(AlarmSchedule $alarm): void
    {
        $configuration = AlarmConfiguration::make($alarm->id)
            ->at($alarm->time)
            ->label($alarm->label)
            ->repeatOn($this->weekdays($alarm->weekdays))
            ->vibration($alarm->vibration)
            ->metadata([
                'route' => "/challenge/{$alarm->id}/{$alarm->executionId}/{$alarm->scheduledFor}",
                'execution_id' => $alarm->executionId,
                'scheduled_for' => $alarm->scheduledFor,
                'notification_title' => $alarm->notificationTitle,
                'notification_body' => $alarm->notificationBody,
            ]);

        if ($alarm->snoozeEnabled) {
            $configuration = $configuration->snooze($alarm->snoozeMinutes);
        }

        $this->alarms->schedule($configuration);
    }

    public function complete(string $alarmId): void
    {
        $this->alarms->complete($alarmId);
    }

    public function cancel(string $alarmId): void
    {
        $this->alarms->cancel($alarmId);
    }

    public function snooze(string $alarmId, int $minutes): void
    {
        $this->alarms->snooze($alarmId, $minutes);
    }

    /** @param list<int> $weekdays @return list<Weekday> */
    private function weekdays(array $weekdays): array
    {
        foreach ($weekdays as $weekday) {
            if ($weekday < 1 || $weekday > 7) {
                throw new \InvalidArgumentException('Weekdays must use ISO values from 1 to 7.');
            }
        }

        return array_map(fn (int $weekday): Weekday => Weekday::cases()[$weekday - 1], $weekdays);
    }

    /** @param array<string, mixed> $event */
    private function occurrenceEvent(array $event): ?NativeAlarmOccurrenceEvent
    {
        $alarmId = $event['alarm_id'] ?? null;
        $executionId = $event['execution_id'] ?? null;
        $scheduledFor = $event['scheduled_for'] ?? null;
        $status = $event['status'] ?? null;
        $occurredAt = $event['occurred_at'] ?? null;

        if (! is_string($alarmId) || ! is_string($executionId) || ! is_string($scheduledFor) || ! is_string($status) || ! is_string($occurredAt)) {
            return null;
        }

        return new NativeAlarmOccurrenceEvent($alarmId, $executionId, $scheduledFor, $status, $occurredAt, (int) ($event['snooze_count'] ?? 0));
    }
}
