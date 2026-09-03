<?php

namespace App\Application\AlarmScheduling;

interface NativeAlarmGateway
{
    public function canScheduleExactly(): bool;

    public function requestExactAlarmPermission(): void;

    public function canUseFullScreenIntent(): bool;

    public function requestFullScreenIntentPermission(): void;

    public function canPostNotifications(): bool;

    public function requestNotificationPermission(string $requestId): void;

    public function activeRingingAlarmId(): ?string;

    public function schedule(AlarmSchedule $alarm): void;

    public function complete(string $alarmId): void;

    public function cancel(string $alarmId): void;
}
