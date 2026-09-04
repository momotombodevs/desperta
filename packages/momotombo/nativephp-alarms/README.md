# NativePHP Alarms

`momotombo/nativephp-alarms` is an Android alarm contract for NativePHP Mobile. It keeps application-domain concerns such as challenges, streaks, routes, and statistics outside the plugin.

The package defines the PHP API, DTOs, capabilities, events, typed errors, and Android bridge endpoints. Android uses `AlarmManager.setAlarmClock()` with a receiver, boot rescheduling, and the device's default alarm tone. It does not use PHP timers, periodic jobs, or background scheduling.

Android supports exact alarms, weekly repetition, vibration, notification authorization, snooze, and playback of the system alarm tone. Custom sound identifiers remain unsupported.

## Install

```bash
composer require momotombo/nativephp-alarms
php artisan native:plugin:register momotombo/nativephp-alarms
php artisan native:plugin:validate
```

Native code changes require a native rebuild. Run `php artisan native:run android` yourself after a native implementation is added.

## Contract

```php
use Momotombo\NativePHPAlarms\DTO\AlarmConfiguration;
use Momotombo\NativePHPAlarms\Enums\Weekday;
use Momotombo\NativePHPAlarms\Facades\Alarm;

$alarm = AlarmConfiguration::make('weekday-wake-up')
    ->at('06:30')
    ->repeatOn([Weekday::Monday, Weekday::Tuesday, Weekday::Wednesday])
    ->sound('morning-soft')
    ->vibration()
    ->snooze(5)
    ->metadata(['route' => '/wake-up']);

$capabilities = Alarm::capabilities();
$authorization = Alarm::requestAuthorization();

Alarm::schedule($alarm);
```

The facade also exposes `authorizationStatus`, `notificationAuthorizationStatus`, `canSchedule`, `canPostNotifications`, `update`, `cancel`, `cancelAll`, `snooze`, `next`, `all`, and `exists`. `AlarmConfiguration` validates id, time, unique `Weekday` values, and snooze duration before calling native code.

## Events and errors

Native implementations may dispatch `AlarmAuthorizationChanged`, `NotificationAuthorizationChanged`, `AlarmScheduled`, `AlarmTriggered`, `AlarmSnoozed`, `AlarmCancelled`, `AlarmCompleted`, and `AlarmError`. Consumers can handle native events with NativePHP's `#[On(Event::class)]` support.

Failures are explicit: `AlarmAuthorizationDenied`, `ExactAlarmPermissionDenied`, `InvalidAlarmConfiguration`, `AlarmNotFound`, `NativeAlarmSchedulingFailed`, and `UnsupportedFeature`.

Capabilities report exact scheduling, custom sound, snooze, repeating schedules, system alarm UI, and volume control independently.
