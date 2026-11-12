<?php

namespace Momotombo\NativePHPAlarms\Facades;

use Illuminate\Support\Facades\Facade;
use Momotombo\NativePHPAlarms\AlarmScheduler;
use Momotombo\NativePHPAlarms\DTO\ActiveAlarmOccurrence;
use Momotombo\NativePHPAlarms\DTO\AlarmCapabilities;
use Momotombo\NativePHPAlarms\DTO\AlarmConfiguration;
use Momotombo\NativePHPAlarms\DTO\BedtimeReminderConfiguration;
use Momotombo\NativePHPAlarms\Enums\AuthorizationStatus;

/**
 * Facade for {@see AlarmScheduler}.
 *
 * @method static AlarmCapabilities capabilities()
 * @method static AuthorizationStatus authorizationStatus()
 * @method static AuthorizationStatus requestAuthorization()
 * @method static bool canUseFullScreenIntent()
 * @method static void requestFullScreenIntentAuthorization()
 * @method static AuthorizationStatus notificationAuthorizationStatus()
 * @method static AuthorizationStatus requestNotificationAuthorization(string $requestId)
 * @method static bool canSchedule()
 * @method static bool canPostNotifications()
 * @method static ActiveAlarmOccurrence|null activeRingingOccurrence()
 * @method static list<array{alarm_id: string, occurrence_id: string, scheduled_for: string, status: string, occurred_at: string}> occurrenceEvents()
 * @method static void acknowledgeOccurrences(list<string> $occurrenceIds)
 * @method static void schedule(AlarmConfiguration $configuration)
 * @method static void scheduleBedtimeReminder(BedtimeReminderConfiguration $configuration)
 * @method static void cancelBedtimeReminder()
 * @method static void forgetWidgetAlarm(string $alarmId)
 * @method static void update(AlarmConfiguration $configuration)
 * @method static void complete(string $alarmId)
 * @method static void cancel(string $alarmId)
 * @method static void snooze(string $alarmId, int $minutes)
 */
final class Alarm extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AlarmScheduler::class;
    }
}
