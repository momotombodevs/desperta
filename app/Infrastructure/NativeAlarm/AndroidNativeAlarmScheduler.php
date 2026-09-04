<?php

namespace App\Infrastructure\NativeAlarm;

use App\AlarmScheduling\ActiveAlarmOccurrence;
use App\Application\AlarmScheduling\AlarmSchedule;
use App\Application\AlarmScheduling\NativeAlarmGateway;
use App\Application\AlarmScheduling\NativeAlarmScheduler;

final class AndroidNativeAlarmScheduler implements NativeAlarmScheduler
{
    public function __construct(private NativeAlarmGateway $alarms) {}

    public function canScheduleExactly(): bool
    {
        return $this->alarms->canScheduleExactly();
    }

    public function requestExactAlarmPermission(): void
    {
        $this->alarms->requestExactAlarmPermission();
    }

    public function canPresentWhileLocked(): bool
    {
        return $this->alarms->canUseFullScreenIntent();
    }

    public function requestFullScreenAlarmPermission(): void
    {
        $this->alarms->requestFullScreenIntentPermission();
    }

    public function canPostNotifications(): bool
    {
        return $this->alarms->canPostNotifications();
    }

    public function requestNotificationPermission(string $requestId): void
    {
        $this->alarms->requestNotificationPermission($requestId);
    }

    public function activeRingingOccurrence(): ?ActiveAlarmOccurrence
    {
        return $this->alarms->activeRingingOccurrence();
    }

    public function schedule(AlarmSchedule $alarm): void
    {
        $this->alarms->schedule($alarm);
    }

    public function completeRinging(string $alarmId): void
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
}
