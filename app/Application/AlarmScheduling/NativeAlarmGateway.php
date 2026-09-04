<?php

namespace App\Application\AlarmScheduling;

use App\AlarmScheduling\ActiveAlarmOccurrence;
use App\AlarmScheduling\NativeAlarmOccurrenceEvent;

interface NativeAlarmGateway
{
    public function canScheduleExactly(): bool;

    public function requestExactAlarmPermission(): void;

    public function canUseFullScreenIntent(): bool;

    public function requestFullScreenIntentPermission(): void;

    public function canPostNotifications(): bool;

    public function requestNotificationPermission(string $requestId): void;

    public function activeRingingOccurrence(): ?ActiveAlarmOccurrence;

    /** @return list<NativeAlarmOccurrenceEvent> */
    public function occurrenceEvents(): array;

    /** @param list<string> $executionIds */
    public function acknowledgeOccurrenceEvents(array $executionIds): void;

    public function schedule(AlarmSchedule $alarm): void;

    public function complete(string $alarmId): void;

    public function snooze(string $alarmId, int $minutes): void;

    public function cancel(string $alarmId): void;
}
