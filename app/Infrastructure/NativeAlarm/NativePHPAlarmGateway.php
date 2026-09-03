<?php

namespace App\Infrastructure\NativeAlarm;

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

    public function activeRingingAlarmId(): ?string
    {
        if (! function_exists('nativephp_call')) {
            return null;
        }

        return $this->alarms->activeRingingAlarmId();
    }

    public function schedule(AlarmSchedule $alarm): void
    {
        $configuration = AlarmConfiguration::make($alarm->id)
            ->at($alarm->time)
            ->label($alarm->label)
            ->repeatOn($this->weekdays($alarm->weekdays))
            ->vibration($alarm->vibration)
            ->metadata([
                'route' => "/challenge?alarmId={$alarm->id}&executionId={$alarm->executionId}&scheduledFor={$alarm->scheduledFor}",
                'execution_id' => $alarm->executionId,
                'scheduled_for' => $alarm->scheduledFor,
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
}
