<?php

namespace App\Application\AlarmScheduling;

interface NativeAlarmScheduler
{
    public function canScheduleExactly(): bool;

    public function requestExactAlarmPermission(): void;

    public function canPresentWhileLocked(): bool;

    public function requestFullScreenAlarmPermission(): void;

    public function canPostNotifications(): bool;

    public function requestNotificationPermission(string $requestId): void;

    public function activeRingingAlarmId(): ?string;

    public function schedule(AlarmSchedule $alarm): void;

    public function completeRinging(string $alarmId): void;

    public function cancel(string $alarmId): void;
}
