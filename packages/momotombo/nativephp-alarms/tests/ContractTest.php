<?php

use Momotombo\NativePHPAlarms\AlarmScheduler;
use Momotombo\NativePHPAlarms\Bridge\NativeAlarmBridge;
use Momotombo\NativePHPAlarms\DTO\AlarmConfiguration;
use Momotombo\NativePHPAlarms\DTO\BedtimeReminderConfiguration;
use Momotombo\NativePHPAlarms\Enums\AuthorizationStatus;
use Momotombo\NativePHPAlarms\Enums\Weekday;
use Momotombo\NativePHPAlarms\Events\NotificationAuthorizationChanged;
use Momotombo\NativePHPAlarms\Exceptions\AlarmNotFound;
use Momotombo\NativePHPAlarms\Exceptions\InvalidAlarmConfiguration;
use Momotombo\NativePHPAlarms\Exceptions\UnsupportedFeature;

it('serializes a reusable weekly alarm configuration with an occurrence and launch path', function () {
    $alarm = AlarmConfiguration::make('weekday-wake-up')
        ->at('06:30')
        ->repeatOn([Weekday::Monday, Weekday::Friday])
        ->vibration()
        ->progressiveVolume()
        ->snooze(5)
        ->launchPath('/wake-up')
        ->notification('Wake up', 'Your alarm is ringing.')
        ->occurrence('occurrence-1', '2026-09-03T06:30:00+00:00');

    expect($alarm->toPayload())->toBe([
        'id' => 'weekday-wake-up',
        'hour' => 6,
        'minute' => 30,
        'weekdays' => ['monday', 'friday'],
        'label' => null,
        'vibration' => true,
        'progressive_volume' => true,
        'snooze_minutes' => 5,
        'launch_path' => '/wake-up',
        'notification_title' => 'Wake up',
        'notification_body' => 'Your alarm is ringing.',
        'occurrence_id' => 'occurrence-1',
        'scheduled_for' => '2026-09-03T06:30:00+00:00',
        'alarm_date' => null,
        'snooze_enabled' => true,
    ]);
});

it('rejects invalid alarm configuration values', function (callable $configure, string $message) {
    expect($configure)->toThrow(InvalidAlarmConfiguration::class, $message);
})->with([
    'invalid hour' => [fn (): AlarmConfiguration => new AlarmConfiguration(id: 'wake-up', hour: 24), 'between 0 and 23'],
    'invalid minute' => [fn (): AlarmConfiguration => new AlarmConfiguration(id: 'wake-up', minute: 60), 'between 0 and 59'],
    'duplicate weekday' => [fn (): AlarmConfiguration => new AlarmConfiguration(id: 'wake-up', weekdays: [Weekday::Monday, Weekday::Monday]), 'duplicates'],
    'invalid snooze' => [fn (): AlarmConfiguration => new AlarmConfiguration(id: 'wake-up', snoozeMinutes: 0), 'at least one minute'],
    'invalid launch path' => [fn (): AlarmConfiguration => AlarmConfiguration::make('wake-up')->launchPath('wake-up'), 'begin with a slash'],
    'invalid one-time date' => [fn (): AlarmConfiguration => AlarmConfiguration::make('wake-up')->onDate('2026-02-30'), 'valid YYYY-MM-DD'],
    'one-time and weekly schedule conflict' => [fn (): AlarmConfiguration => AlarmConfiguration::make('wake-up')->repeatOn([Weekday::Monday])->onDate('2026-09-03'), 'without weekly repetition'],
]);

it('serializes a no-snooze alarm for one selected date', function () {
    $payload = AlarmConfiguration::make('important-day')
        ->at('06:30')
        ->onDate('2026-09-03')
        ->snoozeEnabled(false)
        ->toPayload();

    expect($payload['alarm_date'])->toBe('2026-09-03')
        ->and($payload['snooze_enabled'])->toBeFalse()
        ->and($payload['weekdays'])->toBe([]);
});

it('serializes a recurring bedtime notification without alarm playback settings', function () {
    $configuration = new BedtimeReminderConfiguration(
        time: '21:30',
        weekdays: [Weekday::Monday, Weekday::Friday],
        title: 'Get ready for bed',
        body: 'Check tomorrow’s alarm.',
    );

    expect($configuration->toPayload())->toBe([
        'hour' => 21,
        'minute' => 30,
        'weekdays' => ['monday', 'friday'],
        'title' => 'Get ready for bed',
        'body' => 'Check tomorrow’s alarm.',
    ]);
});

it('maps native capabilities and authorization without assuming platform parity', function () {
    $scheduler = new AlarmScheduler(new class implements NativeAlarmBridge
    {
        public function call(string $method, array $parameters = []): array
        {
            return match ($method) {
                'Alarms.Capabilities' => ['exact' => true, 'snooze' => true, 'repeating' => true, 'system_alarm_ui' => true, 'volume_control' => true],
                'Alarms.AuthorizationStatus' => ['status' => 'authorized'],
            };
        }
    });

    expect($scheduler->capabilities()->toPayload())->toBe([
        'exact' => true,
        'snooze' => true,
        'repeating' => true,
        'system_alarm_ui' => true,
        'volume_control' => true,
    ]);
    expect($scheduler->authorizationStatus())->toBe(AuthorizationStatus::Authorized);
    expect($scheduler->canSchedule())->toBeTrue();
});

it('correlates notification authorization requests with their native result event', function () {
    $bridge = new class implements NativeAlarmBridge
    {
        /** @var array<string, mixed> */
        public array $parameters = [];

        public function call(string $method, array $parameters = []): array
        {
            $this->parameters = $parameters;

            return ['status' => 'not_determined'];
        }
    };

    $status = (new AlarmScheduler($bridge))->requestNotificationAuthorization('request-123');
    $event = new NotificationAuthorizationChanged(granted: true, requestId: 'request-123');

    expect($status)->toBe(AuthorizationStatus::NotDetermined)
        ->and($bridge->parameters)->toBe(['requestId' => 'request-123'])
        ->and($event->granted)->toBeTrue()
        ->and($event->requestId)->toBe('request-123');
});

it('keeps completion separate from cancelling a scheduled alarm', function () {
    $bridge = new class implements NativeAlarmBridge
    {
        /** @var list<string> */
        public array $methods = [];

        public function call(string $method, array $parameters = []): array
        {
            $this->methods[] = $method;

            return [];
        }
    };

    $scheduler = new AlarmScheduler($bridge);
    $scheduler->complete('weekday-wake-up');

    expect($bridge->methods)->toBe(['Alarms.Complete']);
});

it('returns the active ringing alarm id without exposing a schedule', function () {
    $scheduler = new AlarmScheduler(new class implements NativeAlarmBridge
    {
        public function call(string $method, array $parameters = []): array
        {
            return $method === 'Alarms.Active'
                ? ['id' => 'weekday-wake-up', 'occurrence_id' => 'occurrence-1', 'scheduled_for' => '2026-09-03T06:30:00+00:00']
                : [];
        }
    });

    expect($scheduler->activeRingingOccurrence()?->occurrenceId)->toBe('occurrence-1');
});

it('translates native error cases into typed exceptions', function (array $response, string $exception) {
    $scheduler = new AlarmScheduler(new class($response) implements NativeAlarmBridge
    {
        /** @param array<string, mixed> $response */
        public function __construct(private array $response) {}

        public function call(string $method, array $parameters = []): array
        {
            return $this->response;
        }
    });

    expect(fn () => $scheduler->cancel('missing'))->toThrow($exception);
})->with([
    'missing alarm' => [['status' => 'error', 'code' => 'alarm_not_found', 'message' => 'Not found'], AlarmNotFound::class],
    'unimplemented bridge' => [['status' => 'error', 'code' => 'not_implemented', 'message' => 'Not implemented'], UnsupportedFeature::class],
]);
