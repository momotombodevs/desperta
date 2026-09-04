<?php

namespace Momotombo\NativePHPAlarms\Facades;

use Illuminate\Support\Facades\Facade;
use Momotombo\NativePHPAlarms\AlarmScheduler;
use Momotombo\NativePHPAlarms\DTO\AlarmCapabilities;
use Momotombo\NativePHPAlarms\DTO\AlarmConfiguration;
use Momotombo\NativePHPAlarms\Enums\AuthorizationStatus;

/**
 * @method static AlarmCapabilities capabilities()
 * @method static AuthorizationStatus authorizationStatus()
 * @method static AuthorizationStatus requestAuthorization()
 * @method static bool canUseFullScreenIntent()
 * @method static void requestFullScreenIntentAuthorization()
 * @method static AuthorizationStatus notificationAuthorizationStatus()
 * @method static AuthorizationStatus requestNotificationAuthorization(string $requestId)
 * @method static bool canSchedule()
 * @method static bool canPostNotifications()
 * @method static void schedule(AlarmConfiguration $configuration)
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
