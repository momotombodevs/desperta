# NativePHP Alarms

`momotombo/nativephp-alarms` is an Android-native alarm contract for NativePHP Mobile. It owns exact scheduling, alarm playback, permission entry points, and a durable occurrence journal. It deliberately does not know about a product's challenges, habits, streaks, statistics, or routes beyond carrying a caller-provided native launch path.

## Platform support

| Platform | Support | Notes |
| --- | --- | --- |
| Android | API 26+ | Full implementation: exact `AlarmManager` alarms, foreground playback, vibration, weekly repetition, boot rescheduling, notification permission, and snooze. |
| iOS | Not implemented | The manifest retains `ios.min_version` because the NativePHP manifest validator requires it, but there are no iOS bridge functions or native sources. Treat the plugin as Android-only. |

Android declares `SCHEDULE_EXACT_ALARM`, `USE_FULL_SCREEN_INTENT`, `POST_NOTIFICATIONS`, `RECEIVE_BOOT_COMPLETED`, `VIBRATE`, and foreground media-playback permissions. Exact-alarm and notification access remain user and OS decisions, so callers must check their status before relying on them.

## Install and register

```bash
composer require momotombo/nativephp-alarms
php artisan native:plugin:register momotombo/nativephp-alarms
php artisan native:plugin:validate
```

Changes to `resources/android/` or `nativephp.json` are native-shell changes and require Android device or emulator acceptance after the app is rebuilt.

## Quick start

```php
use Momotombo\NativePHPAlarms\DTO\AlarmConfiguration;
use Momotombo\NativePHPAlarms\Enums\Weekday;
use Momotombo\NativePHPAlarms\Facades\Alarm;

$alarm = AlarmConfiguration::make('weekday-wake-up')
    ->at('06:30')
    ->repeatOn([Weekday::Monday, Weekday::Tuesday, Weekday::Wednesday])
    ->label('Wake up')
    ->vibration()
    ->snooze(5)
    ->launchPath('/wake-up')
    ->notification('Wake up', 'Your alarm is ringing.')
    ->occurrence('occurrence-123', '2026-09-03T06:30:00+00:00');

if (Alarm::canSchedule()) {
    Alarm::schedule($alarm);
}
```

The `occurrence()` values are supplied by the application domain. They make reconciliation idempotent: do not reuse an occurrence ID for a distinct intended ring.

## Public PHP API

`Alarm` is the facade for the singleton `AlarmScheduler`. The same scheduler can be constructor-injected when a facade is not desirable.

| Method | Result | Purpose |
| --- | --- | --- |
| `capabilities()` | `AlarmCapabilities` | Reads independent native feature flags. |
| `authorizationStatus()` | `AuthorizationStatus` | Reads exact-alarm authorization. |
| `requestAuthorization()` | `AuthorizationStatus` | Opens Android exact-alarm settings when needed. A later status read is authoritative. |
| `canUseFullScreenIntent()` | `bool` | Reads full-screen alarm authorization. |
| `requestFullScreenIntentAuthorization()` | `void` | Opens full-screen settings where Android exposes them. |
| `notificationAuthorizationStatus()` | `AuthorizationStatus` | Reads notification authorization. |
| `requestNotificationAuthorization(string $requestId)` | `AuthorizationStatus` | Starts the Android notification permission request. `requestId` correlates the resulting native event. |
| `canPostNotifications()` | `bool` | True only when notification status is `Authorized`. |
| `canSchedule()` | `bool` | True only when exact alarms are supported and authorized. |
| `activeRingingOccurrence()` | `?ActiveAlarmOccurrence` | Returns the currently ringing occurrence, if any. |
| `occurrenceEvents()` | `list<array<string, mixed>>` | Reads unacknowledged lifecycle entries from the device journal. |
| `acknowledgeOccurrences(array $occurrenceIds)` | `void` | Deletes only reconciled journal entries. Call after the application commits its durable state. |
| `schedule(AlarmConfiguration $configuration)` | `void` | Stores and schedules an alarm. |
| `update(AlarmConfiguration $configuration)` | `void` | Replaces an alarm's stored configuration and next native schedule. |
| `complete(string $alarmId)` | `void` | Stops the active ringing session without cancelling a future repeating schedule. |
| `cancel(string $alarmId)` | `void` | Stops playback, cancels pending normal and snooze alarms, removes stored state, and removes its notification. |
| `snooze(string $alarmId, int $minutes)` | `void` | Stops an active ringing occurrence and schedules it once after the requested delay. It rejects a non-ringing or unknown alarm. |

### DTOs and enums

| Type | Public fields or methods | Contract |
| --- | --- | --- |
| `AlarmConfiguration` | `make`, `at`, `repeatOn`, `label`, `vibration`, `progressiveVolume`, `snooze`, `launchPath`, `notification`, `occurrence`, `toPayload`, `fromPayload` | Immutable alarm payload. `at()` requires `HH:MM`; weekdays must be unique `Weekday` enum cases; snooze is at least one minute; a launch path begins with `/`; occurrence ID and scheduled time cannot be blank. |
| `AlarmCapabilities` | `exact`, `snooze`, `repeating`, `systemAlarmUi`, `volumeControl` | Read-only feature matrix. `volumeControl` is available through the opt-in progressive-volume ramp. |
| `ActiveAlarmOccurrence` | `alarmId`, `occurrenceId`, `scheduledFor`, `fromPayload` | Returns `null` from `fromPayload()` unless all three values are present and non-empty. |
| `AuthorizationStatus` | `NotDetermined`, `Authorized`, `Denied`, `Unsupported` | Exact-alarm and notification authorization values. |
| `Weekday` | `Monday` through `Sunday` | The only accepted values for repeating schedules. |

### Payload contract

`AlarmConfiguration::toPayload()` sends the following keys to Android:

| Key | Type | Meaning |
| --- | --- | --- |
| `id` | string | Stable application alarm ID. |
| `hour`, `minute` | integer | Local wall-clock time. |
| `weekdays` | list of strings | Empty for one-shot; otherwise lowercase `Weekday` values. |
| `label` | nullable string | Human-readable fallback notification title. |
| `vibration` | boolean | Whether playback should vibrate. |
| `progressive_volume` | boolean | Whether Android ramps playback from 20% to 100% over 30 seconds without changing the device volume. |
| `snooze_minutes` | nullable integer | Application-selected default snooze duration; the actual snooze call also takes minutes. |
| `launch_path` | nullable string | NativePHP route path to open when the alarm rings. |
| `notification_title`, `notification_body` | nullable strings | Notification copy; title falls back to `label`, then `Alarm`. |
| `occurrence_id`, `scheduled_for` | nullable strings | Application correlation data used for lifecycle reconciliation. |

The plugin uses the device's default alarm tone. `progressiveVolume()` opts a configuration into the native 20%-to-100% ramp. It does not accept a custom sound payload or expose a browser/JavaScript bridge.

## Lifecycle and reconciliation

```text
application occurrence + AlarmConfiguration
        -> Schedule / Update
        -> Android stores configuration and schedules AlarmManager
        -> AlarmReceiver records `triggered` and starts playback
        -> application reads occurrenceEvents() and commits its own state
        -> acknowledgeOccurrences([occurrenceId])
        -> Complete, Snooze, or Cancel records a final state when applicable
```

For a repeating alarm, Android creates a new UUID occurrence for the next ring and schedules it before starting playback for the current one. The device journal entries contain:

```php
[
    'alarm_id' => 'weekday-wake-up',
    'occurrence_id' => '...',
    'scheduled_for' => '2026-09-03T06:30:00Z',
    'status' => 'scheduled|triggered|snoozed|completed|cancelled',
    'occurred_at' => '2026-09-03T06:30:00Z',
]
```

The journal is device-local and intentionally retains entries until acknowledged. An application should make its own persistence idempotent by `occurrence_id`, then acknowledge only the entries it committed. `activeRingingOccurrence()` is a present-time query; it is not a replacement for reconciliation.

## Native implementation map

| Android class or component | Responsibility |
| --- | --- |
| `AlarmsFunctions` | NativePHP bridge endpoints, authorization checks, schedule/update/complete/cancel/snooze orchestration. |
| `AlarmReceiver` | Receives normal and snooze alarms, advances repeating schedules, journals a trigger, starts playback, and opens the supplied launch path when unlocked. |
| `AlarmPlaybackService` | Foreground service that loops the system alarm tone, optionally vibrates, and displays the ringing notification. |
| `AlarmActivity` | Locked-screen handoff that opens the supplied native route. |
| `BootReceiver` | Restores stored exact alarms after device boot when authorization still exists. |
| `AlarmStore`, `SnoozeStore`, `TriggeredAlarmStore` | Internal SharedPreferences persistence for scheduled, snoozed, and ringing state. |
| `OccurrenceJournal` | Internal durable, acknowledgement-based occurrence event store. |
| `NotificationIds` | Allocates app-private sequential notification IDs, avoiding Java `hashCode()` notification collisions. |

`NativeAlarmBridge` is the replaceable PHP boundary with one `call(string $method, array $parameters = [])` method. `NativePHPAlarmBridge` is its production implementation over `nativephp_call()`. Use a fake bridge in package or application tests; do not make application domain code depend directly on Kotlin bridge names.

## Native bridge endpoints

The manifest exposes these Android-only endpoints: `Alarms.Capabilities`, `AuthorizationStatus`, `RequestAuthorization`, `FullScreenIntentAuthorizationStatus`, `RequestFullScreenIntentAuthorization`, `NotificationAuthorizationStatus`, `RequestNotificationAuthorization`, `Active`, `Schedule`, `Update`, `Occurrences`, `AcknowledgeOccurrences`, `Complete`, `Cancel`, and `Snooze` (all under the `Alarms.` namespace).

Consumers should use `AlarmScheduler` or the `Alarm` facade rather than calling those names directly. The scheduler maps native error codes into typed exceptions:

| Exception | When raised |
| --- | --- |
| `AlarmAuthorizationDenied` | Android reports alarm authorization denied. |
| `ExactAlarmPermissionDenied` | Exact scheduling is unavailable. |
| `AlarmNotFound` | An active alarm required by a native operation is absent; notably non-ringing snooze attempts. |
| `UnsupportedFeature` | The native implementation explicitly does not support a requested capability. |
| `InvalidAlarmConfiguration` | PHP validation rejects invalid IDs, time, weekdays, paths, or snooze duration. |
| `NativeAlarmSchedulingFailed` | The bridge is unavailable, malformed, or returns another native failure. |

## Event contract

The only plugin event is `Momotombo\NativePHPAlarms\Events\NotificationAuthorizationChanged`. Android emits it once a `POST_NOTIFICATIONS` request resolves:

```php
use Livewire\Attributes\On;
use Momotombo\NativePHPAlarms\Events\NotificationAuthorizationChanged;

#[On(NotificationAuthorizationChanged::class)]
public function notificationAuthorizationChanged(bool $granted, string $requestId): void
{
    // Match requestId to the UI state that started the request.
}
```

There are no `AlarmTriggered`, `AlarmCompleted`, `AlarmError`, or similar plugin events. Use the occurrence journal for alarm lifecycle facts.

## Validation

```bash
php artisan native:plugin:validate packages/momotombo/nativephp-alarms --no-interaction
vendor/bin/pest packages/momotombo/nativephp-alarms/tests --compact
```

These validate the PHP contract and manifest. They do not prove Android scheduling, boot handling, permission flows, locked-screen behavior, or audio playback; those require Android emulator or physical-device acceptance after a native rebuild.

## License

MIT
