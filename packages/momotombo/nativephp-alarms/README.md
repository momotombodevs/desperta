<p align="center">
    <a href="https://momotombo.dev">
        <img src="https://raw.githubusercontent.com/momotombodevs/nativephp-alarms/main/art/momotombo-logo.svg" alt="Momotombo Devs" width="120">
    </a>
</p>

<h1 align="center">NativePHP Alarms</h1>

<p align="center">
    Exact, alarm-clock-grade alarms for <a href="https://nativephp.com/mobile">NativePHP Mobile</a> on Android.
</p>

<p align="center">
    <a href="https://packagist.org/packages/momotombo/nativephp-alarms"><img src="https://img.shields.io/packagist/v/momotombo/nativephp-alarms.svg" alt="Latest version"></a>
    <a href="https://packagist.org/packages/momotombo/nativephp-alarms"><img src="https://img.shields.io/packagist/dt/momotombo/nativephp-alarms.svg" alt="Total downloads"></a>
    <a href="LICENSE"><img src="https://img.shields.io/packagist/l/momotombo/nativephp-alarms.svg" alt="License"></a>
</p>

---

`momotombo/nativephp-alarms` gives a NativePHP Mobile app real alarms: exact `AlarmManager` scheduling, a looping alarm tone in a foreground service, snooze, weekly repetition, restore after reboot, and a durable occurrence journal your app reconciles against.

The plugin handles only the device side. It knows nothing about your challenges, habits, streaks, or routes. It carries the launch path you give it, and your app keeps its own domain state.

## Apps using this plugin

| App | Description |
| --- | --- |
| [**Despertá**](https://desperta.momotombo.dev/) · [Google Play](https://play.google.com/store/apps/details?id=dev.momotombo.desperta) | A daily alarm you can only turn off by solving a short three-question challenge. Progress stays on the device and no account is needed. |

Using the plugin in your app? Open a pull request to add it here.

## Features

- Exact alarms through `AlarmManager.setAlarmClock()`, which shows in the system status bar and is exempt from Doze.
- Loops the device's default alarm tone in a foreground service, with optional vibration and an optional progressive volume ramp.
- Weekly repetition, or a one-time alarm on a specific date.
- Configurable snooze, which can be turned off per alarm.
- Full-screen intent and lock-screen handoff to a NativePHP route.
- Restores alarms and pending snoozes after reboot, time or time zone changes, and when exact-alarm permission is granted.
- Acknowledgement-based occurrence journal for idempotent reconciliation.
- Optional recurring bedtime reminder notification.
- Optional home-screen widget that shows the next alarm.

## Requirements

| Requirement | Version |
| --- | --- |
| PHP | 8.3+ |
| NativePHP Mobile | 4.6.1+ |
| Android | 8.0+ (API 26) |
| iOS | Not supported |

The manifest keeps `ios.min_version` only because the NativePHP manifest validator requires it. There are no iOS bridge functions or native sources, so treat the plugin as Android-only.

NativePHP Mobile 4.6.1 is the minimum because the plugin's events implement `Native\Mobile\Events\Contracts\DispatchableFromNative`. Newer NativePHP versions refuse to dispatch native events that don't implement it.

## Installation

```bash
composer require momotombo/nativephp-alarms
php artisan native:plugin:register momotombo/nativephp-alarms
php artisan native:plugin:validate
```

Rebuild the native app afterwards, for example with `php artisan native:run android`. Any change to `resources/android/` or `nativephp.json` is a native-shell change: it needs a rebuild and should be accepted on an emulator or a physical device.

### Android permissions

The plugin declares these permissions:

`SCHEDULE_EXACT_ALARM`, `USE_FULL_SCREEN_INTENT`, `POST_NOTIFICATIONS`, `RECEIVE_BOOT_COMPLETED`, `VIBRATE`, `FOREGROUND_SERVICE`, and `FOREGROUND_SERVICE_MEDIA_PLAYBACK`.

The user and the OS still decide on exact-alarm, full-screen, and notification access. Check each status before relying on it, as the quick start shows.

## Quick start

```php
use Momotombo\NativePHPAlarms\DTO\AlarmConfiguration;
use Momotombo\NativePHPAlarms\Enums\AuthorizationStatus;
use Momotombo\NativePHPAlarms\Enums\Weekday;
use Momotombo\NativePHPAlarms\Facades\Alarm;

// 1. Make sure the device lets you schedule exact alarms.
if (! Alarm::canSchedule()) {
    Alarm::requestAuthorization(); // Opens Android settings. Read the status again on resume.

    return;
}

// 2. Describe the alarm.
$alarm = AlarmConfiguration::make('weekday-wake-up')
    ->at('06:30')
    ->repeatOn([Weekday::Monday, Weekday::Tuesday, Weekday::Wednesday])
    ->label('Wake up')
    ->vibration()
    ->progressiveVolume()
    ->snooze(5)
    ->launchPath('/wake-up')
    ->notification('Wake up', 'Your alarm is ringing.')
    ->occurrence('occurrence-123', '2026-09-03T06:30:00+00:00');

// 3. Schedule it.
Alarm::schedule($alarm);

// A one-time alarm on a specific date, without snooze:
Alarm::schedule(
    AlarmConfiguration::make('important-day')
        ->at('06:30')
        ->onDate('2026-09-10')
        ->snoozeEnabled(false),
);
```

The `occurrence()` values come from your application domain and make reconciliation idempotent. Don't reuse an occurrence ID for a different ring.

`AlarmConfiguration` is immutable, so every method returns a new instance. Passing `null` to `label()`, `launchPath()`, `notification()`, or `onDate()` clears that value.

## PHP API

`Alarm` is the facade for the singleton `AlarmScheduler`. You can also inject `AlarmScheduler` through the constructor.

| Method | Returns | Purpose |
| --- | --- | --- |
| `capabilities()` | `AlarmCapabilities` | Reads the native feature flags. Each flag is independent. |
| `authorizationStatus()` | `AuthorizationStatus` | Reads exact-alarm authorization. |
| `requestAuthorization()` | `AuthorizationStatus` | Opens Android's exact-alarm settings when needed. Only a later status read is authoritative. |
| `canUseFullScreenIntent()` | `bool` | Reads full-screen alarm authorization. |
| `requestFullScreenIntentAuthorization()` | `void` | Opens full-screen intent settings on Android 14+. |
| `notificationAuthorizationStatus()` | `AuthorizationStatus` | Reads notification authorization. |
| `requestNotificationAuthorization(string $requestId)` | `AuthorizationStatus` | Starts the `POST_NOTIFICATIONS` request. `requestId` correlates the resulting event. A newer request replaces an unresolved one. |
| `canPostNotifications()` | `bool` | True only when notification status is `Authorized`. |
| `canSchedule()` | `bool` | True only when exact alarms are supported and authorized. |
| `activeRingingOccurrence()` | `?ActiveAlarmOccurrence` | Returns the occurrence ringing right now, if any. |
| `occurrenceEvents()` | `list<array>` | Reads unacknowledged lifecycle entries from the device journal. |
| `acknowledgeOccurrences(array $occurrenceIds)` | `void` | Deletes journal entries you have already reconciled. Call it after your app commits its own state. |
| `schedule(AlarmConfiguration $configuration)` | `void` | Stores and schedules an alarm. Replaces an alarm with the same ID and any snooze it has pending. |
| `update(AlarmConfiguration $configuration)` | `void` | Same behavior as `schedule()`. Use it when the intent is to replace an existing alarm. |
| `complete(string $alarmId)` | `void` | Stops the current ring without cancelling future repetitions. |
| `cancel(string $alarmId)` | `void` | Stops playback, cancels pending normal and snooze alarms, and removes the alarm's stored state and notification. |
| `snooze(string $alarmId, int $minutes)` | `void` | Stops the current ring and schedules it once more after the given delay. |
| `scheduleBedtimeReminder(BedtimeReminderConfiguration $configuration)` | `void` | Schedules a silent, recurring bedtime notification. The app has one reminder at a time. |
| `cancelBedtimeReminder()` | `void` | Cancels the bedtime reminder and removes its configuration. |
| `forgetWidgetAlarm(string $alarmId)` | `void` | Removes a deleted alarm from the widget's remembered state. |

### DTOs and enums

| Type | API | Contract |
| --- | --- | --- |
| `AlarmConfiguration` | `make`, `at`, `repeatOn`, `onDate`, `label`, `vibration`, `progressiveVolume`, `snooze`, `snoozeEnabled`, `launchPath`, `notification`, `occurrence`, `toPayload`, `fromPayload` | `at()` requires `HH:MM`. Weekdays must be unique `Weekday` cases. `onDate()` takes `YYYY-MM-DD` and can't be combined with weekly repetition. Snooze is at least one minute. A launch path must start with `/`. Occurrence ID and scheduled time can't be blank. |
| `BedtimeReminderConfiguration` | `__construct(time, weekdays, title, body)`, `toPayload` | `time` is `HH:MM`. `weekdays` holds at least one unique `Weekday`. |
| `AlarmCapabilities` | `exact`, `snooze`, `repeating`, `systemAlarmUi`, `volumeControl` | Read-only feature flags. `volumeControl` refers to the optional progressive volume ramp. |
| `ActiveAlarmOccurrence` | `alarmId`, `occurrenceId`, `scheduledFor` | `fromPayload()` returns `null` unless all three values are present. |
| `AuthorizationStatus` | `NotDetermined`, `Authorized`, `Denied`, `Unsupported` | Unknown native values map to `Unsupported`. |
| `Weekday` | `Monday` … `Sunday` | The only accepted values for repetition. |

### Exceptions

All exceptions extend `Momotombo\NativePHPAlarms\Exceptions\AlarmException`.

| Exception | Raised when |
| --- | --- |
| `InvalidAlarmConfiguration` | PHP validation rejects a value, or Android rejects the payload. One example: a one-time date that has already passed in the device's time zone (`alarm_in_past`). |
| `ExactAlarmPermissionDenied` | Exact scheduling isn't allowed. |
| `AlarmAuthorizationDenied` | Android reports that alarm authorization was denied. |
| `AlarmNotFound` | The operation needs a ringing alarm and none is ringing, as when snoozing an alarm that isn't ringing. |
| `UnsupportedFeature` | The native side doesn't support the request. This includes snoozing an alarm scheduled with `snoozeEnabled(false)`. |
| `NativeAlarmSchedulingFailed` | The bridge is unavailable, returns malformed JSON, or reports any other native failure. |

## Lifecycle and reconciliation

```text
application occurrence + AlarmConfiguration
        -> schedule() / update()
        -> Android stores the configuration and arms AlarmManager
        -> AlarmReceiver records `triggered` and starts playback
        -> your app reads occurrenceEvents() and commits its own state
        -> acknowledgeOccurrences([occurrenceId])
        -> complete(), snooze(), or cancel() records the final status
```

For a repeating alarm, Android creates a UUID occurrence for the next ring and schedules it before playback of the current one starts. Journal entries look like this:

```php
[
    'alarm_id' => 'weekday-wake-up',
    'occurrence_id' => '...',
    'scheduled_for' => '2026-09-03T06:30:00Z',
    'status' => 'scheduled|triggered|snoozed|completed|cancelled',
    'occurred_at' => '2026-09-03T06:30:00Z',
]
```

The journal lives on the device and keeps **one entry per occurrence ID, holding its latest status**, until you acknowledge it. Make your own persistence idempotent by `occurrence_id`, and acknowledge only what you have committed. `activeRingingOccurrence()` answers what is ringing right now; it doesn't replace reconciliation.

### Reboots and clock changes

After a reboot, a time or time zone change, or a grant of exact-alarm permission, the plugin re-arms every stored alarm and any snooze that is still pending. A one-time alarm whose date passed while the device was off is dropped and doesn't ring late.

## Events

Both events implement `DispatchableFromNative`, so NativePHP delivers them to Livewire `#[On]` handlers and Laravel listeners.

| Event | Payload | When |
| --- | --- | --- |
| `NotificationAuthorizationChanged` | `bool $granted`, `string $requestId` | A `requestNotificationAuthorization()` request resolves. |
| `AppResumed` | none | Android resumes the app while a native screen is active. Use it to re-read permissions after sending the user to Settings. |

```php
use Livewire\Attributes\On;
use Momotombo\NativePHPAlarms\Events\NotificationAuthorizationChanged;

#[On(NotificationAuthorizationChanged::class)]
public function notificationAuthorizationChanged(bool $granted, string $requestId): void
{
    // Match $requestId to the UI state that started the request.
}
```

There are no `AlarmTriggered` or `AlarmCompleted` events. Alarm lifecycle facts come from the occurrence journal.

## Home-screen widget

The plugin ships an optional Android widget, `AlarmWidgetProvider`. It shows the next alarm and has a single action button. The widget uses the host app's launcher name. Its copy is in Spanish (`values/`) and English (`values-en/`).

The action button opens this route in your app, so **the app must define it** if it offers the widget:

```text
/quick-actions/alarms/{alarmId}/toggle
```

When the user deletes an alarm, call `Alarm::forgetWidgetAlarm($id)` so the widget stops offering to reactivate it.

Resources are merged into the host app under the names `alarm_widget*`, `widget_*`, and `ic_stat_alarm`. Avoid those names in your own resources, or override them deliberately to restyle the widget.

## Native implementation map

| Android component | Responsibility |
| --- | --- |
| `AlarmsFunctions` | NativePHP bridge endpoints, authorization checks, and the schedule, update, complete, cancel, and snooze operations. |
| `AlarmReceiver` | Receives normal and snooze alarms, advances repeating schedules, journals the trigger, starts playback, and opens the launch path when the device is unlocked. |
| `AlarmPlaybackService` | Foreground service that loops the alarm tone (falling back to the ringtone or notification sound), vibrates if enabled, and shows the ringing notification. |
| `AlarmActivity` | Lock-screen handoff that opens the alarm's launch path. |
| `BootReceiver` | Restores alarms and snoozes after boot, clock changes, and exact-alarm permission grants. |
| `BedtimeReminderReceiver` | Posts the bedtime notification and arms the next one. |
| `AlarmWidgetProvider` | Home-screen widget. |
| `AlarmStore`, `SnoozeStore`, `TriggeredAlarmStore`, `WidgetAlarmStore` | Internal SharedPreferences persistence. |
| `OccurrenceJournal` | Durable occurrence store that keeps entries until they are acknowledged. |
| `NotificationIds` | Sequential, app-private notification IDs, which avoid `hashCode()` collisions. |

The bridge exposes these Android endpoints, all under the `Alarms.` namespace: `Capabilities`, `AuthorizationStatus`, `RequestAuthorization`, `FullScreenIntentAuthorizationStatus`, `RequestFullScreenIntentAuthorization`, `NotificationAuthorizationStatus`, `RequestNotificationAuthorization`, `Active`, `Schedule`, `Update`, `Occurrences`, `AcknowledgeOccurrences`, `Complete`, `Cancel`, `Snooze`, `ScheduleBedtimeReminder`, `CancelBedtimeReminder`, and `ForgetWidgetAlarm`. Call them through `AlarmScheduler` or the facade, not by name.

## Testing your app

`NativeAlarmBridge` is the replaceable PHP boundary, with a single `call(string $method, array $parameters = []): array` method. Bind a fake in tests:

```php
use Momotombo\NativePHPAlarms\Bridge\NativeAlarmBridge;

$this->app->instance(NativeAlarmBridge::class, new class implements NativeAlarmBridge {
    public array $calls = [];

    public function call(string $method, array $parameters = []): array
    {
        $this->calls[] = [$method, $parameters];

        return match ($method) {
            'Alarms.Capabilities' => ['exact' => true, 'snooze' => true, 'repeating' => true],
            'Alarms.AuthorizationStatus' => ['status' => 'authorized'],
            default => [],
        };
    }
});
```

## Development

```bash
composer install
composer test
php artisan native:plugin:validate /path/to/nativephp-alarms --no-interaction   # from a NativePHP app
```

These commands check the PHP contract and the manifest. They can't prove Android scheduling, boot handling, permission flows, lock-screen behavior, or audio playback. Accept those on an emulator or a physical device after a native rebuild.

## Credits

Built and maintained by [Momotombo Devs](https://momotombo.dev).

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
