## momotombo/nativephp-alarms

Cross-platform native alarm contract for NativePHP Mobile.

### Installation

```bash
composer require momotombo/nativephp-alarms
```

### PHP Usage (Livewire/Blade)

Use the `Alarm` facade:

@verbatim
<code-snippet name="Using Alarms Facade" lang="php">
use Momotombo\NativePHPAlarms\Facades\Alarm;
// Return native alarm capabilities.
$result = Alarm::capabilities();
// Return alarm authorization status.
$result = Alarm::authorizationStatus();
// Request alarm authorization.
$result = Alarm::requestAuthorization();
// Schedule an alarm configuration.
$result = Alarm::schedule();
// Update an existing alarm configuration.
$result = Alarm::update();
// Cancel one alarm.
$result = Alarm::cancel();
// Cancel every alarm.
$result = Alarm::cancelAll();
// Snooze one active alarm.
$result = Alarm::snooze();
// Return the next scheduled alarm.
$result = Alarm::next();
// Return all scheduled alarms.
$result = Alarm::all();
// Check whether an alarm exists.
$result = Alarm::exists();
</code-snippet>
@endverbatim

### Available Methods

- `Alarm::capabilities()`: Return native alarm capabilities.
- `Alarm::authorizationStatus()`: Return alarm authorization status.
- `Alarm::requestAuthorization()`: Request alarm authorization.
- `Alarm::schedule()`: Schedule an alarm configuration.
- `Alarm::update()`: Update an existing alarm configuration.
- `Alarm::cancel()`: Cancel one alarm.
- `Alarm::cancelAll()`: Cancel every alarm.
- `Alarm::snooze()`: Snooze one active alarm.
- `Alarm::next()`: Return the next scheduled alarm.
- `Alarm::all()`: Return all scheduled alarms.
- `Alarm::exists()`: Check whether an alarm exists.

### Events

- `AlarmAuthorizationChanged`: Listen with `#[OnNative(AlarmAuthorizationChanged::class)]`
- `AlarmScheduled`: Listen with `#[OnNative(AlarmScheduled::class)]`
- `AlarmTriggered`: Listen with `#[OnNative(AlarmTriggered::class)]`
- `AlarmSnoozed`: Listen with `#[OnNative(AlarmSnoozed::class)]`
- `AlarmCancelled`: Listen with `#[OnNative(AlarmCancelled::class)]`
- `AlarmCompleted`: Listen with `#[OnNative(AlarmCompleted::class)]`
- `AlarmError`: Listen with `#[OnNative(AlarmError::class)]`


@verbatim
<code-snippet name="Listening for Alarms Events" lang="php">
use Native\Mobile\Attributes\OnNative;
#[OnNative(AlarmAuthorizationChanged::class)]
public function handleAlarmAuthorizationChanged($data)
{
    // Handle the event
}
#[OnNative(AlarmScheduled::class)]
public function handleAlarmScheduled($data)
{
    // Handle the event
}
#[OnNative(AlarmTriggered::class)]
public function handleAlarmTriggered($data)
{
    // Handle the event
}
#[OnNative(AlarmSnoozed::class)]
public function handleAlarmSnoozed($data)
{
    // Handle the event
}
#[OnNative(AlarmCancelled::class)]
public function handleAlarmCancelled($data)
{
    // Handle the event
}
#[OnNative(AlarmCompleted::class)]
public function handleAlarmCompleted($data)
{
    // Handle the event
}
#[OnNative(AlarmError::class)]
public function handleAlarmError($data)
{
    // Handle the event
}
</code-snippet>
@endverbatim

### JavaScript Usage (Vue/React/Inertia)

@verbatim
<code-snippet name="Using Alarms in JavaScript" lang="javascript">
import { alarms } from '@momotombo/nativephp-alarms';
// Return native alarm capabilities.
const result = await alarms.capabilities();
// Return alarm authorization status.
const result = await alarms.authorizationStatus();
// Request alarm authorization.
const result = await alarms.requestAuthorization();
// Schedule an alarm configuration.
const result = await alarms.schedule();
// Update an existing alarm configuration.
const result = await alarms.update();
// Cancel one alarm.
const result = await alarms.cancel();
// Cancel every alarm.
const result = await alarms.cancelAll();
// Snooze one active alarm.
const result = await alarms.snooze();
// Return the next scheduled alarm.
const result = await alarms.next();
// Return all scheduled alarms.
const result = await alarms.all();
// Check whether an alarm exists.
const result = await alarms.exists();
</code-snippet>
@endverbatim