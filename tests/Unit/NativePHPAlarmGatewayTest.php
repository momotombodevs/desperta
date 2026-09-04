<?php

use App\Application\AlarmScheduling\AlarmSchedule;
use App\Infrastructure\NativeAlarm\NativePHPAlarmGateway;
use Momotombo\NativePHPAlarms\AlarmScheduler;
use Momotombo\NativePHPAlarms\Bridge\NativeAlarmBridge;

it('maps an application alarm schedule to the plugin configuration', function () {
    $bridge = new class implements NativeAlarmBridge
    {
        /** @var array<string, mixed> */
        public array $parameters = [];

        public function call(string $method, array $parameters = []): array
        {
            $this->parameters = $parameters;

            return [];
        }
    };

    (new NativePHPAlarmGateway(new AlarmScheduler($bridge)))->schedule(new AlarmSchedule(
        id: 'wake-up',
        time: '06:30',
        label: 'Trabajo',
        weekdays: [1, 5],
        vibration: true,
        snoozeEnabled: true,
        difficulty: 'Normal',
        executionId: '018f0b8d-1d3e-7f14-8caa-111111111111',
        scheduledFor: '2026-09-03T06:30:00+00:00',
        notificationTitle: 'Trabajo',
        notificationBody: 'Es hora de despertar.',
    ));

    expect($bridge->parameters)->toBe([
        'id' => 'wake-up',
        'hour' => 6,
        'minute' => 30,
        'weekdays' => ['monday', 'friday'],
        'label' => 'Trabajo',
        'sound' => null,
        'vibration' => true,
        'snooze_minutes' => 5,
        'metadata' => [
            'route' => '/challenge/wake-up/018f0b8d-1d3e-7f14-8caa-111111111111/2026-09-03T06:30:00+00:00',
            'execution_id' => '018f0b8d-1d3e-7f14-8caa-111111111111',
            'scheduled_for' => '2026-09-03T06:30:00+00:00',
            'notification_title' => 'Trabajo',
            'notification_body' => 'Es hora de despertar.',
        ],
    ]);
});

it('uses the plugin notification authorization bridge instead of exact-alarm authorization', function () {
    $bridge = new class implements NativeAlarmBridge
    {
        /** @var list<string> */
        public array $methods = [];

        /** @var array<string, mixed> */
        public array $parameters = [];

        public function call(string $method, array $parameters = []): array
        {
            $this->methods[] = $method;
            $this->parameters = $parameters;

            return ['status' => $method === 'Alarms.NotificationAuthorizationStatus' ? 'authorized' : 'not_determined'];
        }
    };

    $gateway = new NativePHPAlarmGateway(new AlarmScheduler($bridge));

    expect($gateway->canPostNotifications())->toBeTrue();

    $gateway->requestNotificationPermission('request-123');

    expect($bridge->methods)->toBe([
        'Alarms.NotificationAuthorizationStatus',
        'Alarms.RequestNotificationAuthorization',
    ]);

    expect($bridge->parameters)->toBe(['requestId' => 'request-123']);
});

it('uses the plugin full-screen authorization bridge', function () {
    $bridge = new class implements NativeAlarmBridge
    {
        /** @var list<string> */
        public array $methods = [];

        public function call(string $method, array $parameters = []): array
        {
            $this->methods[] = $method;

            return ['authorized' => $method === 'Alarms.FullScreenIntentAuthorizationStatus'];
        }
    };

    $gateway = new NativePHPAlarmGateway(new AlarmScheduler($bridge));

    expect($gateway->canUseFullScreenIntent())->toBeTrue();

    $gateway->requestFullScreenIntentPermission();

    expect($bridge->methods)->toBe([
        'Alarms.FullScreenIntentAuthorizationStatus',
        'Alarms.RequestFullScreenIntentAuthorization',
    ]);
});

it('reads the active ringing occurrence from the native bridge', function () {
    $bridge = new class implements NativeAlarmBridge
    {
        public function call(string $method, array $parameters = []): array
        {
            return $method === 'Alarms.Active'
                ? ['id' => 'wake-up', 'execution_id' => 'execution-1', 'scheduled_for' => '2026-09-03T06:30:00+00:00']
                : [];
        }
    };

    if (! function_exists('nativephp_call')) {
        expect((new NativePHPAlarmGateway(new AlarmScheduler($bridge)))->activeRingingOccurrence())->toBeNull();

        return;
    }

    expect((new NativePHPAlarmGateway(new AlarmScheduler($bridge)))->activeRingingOccurrence()?->alarmId)->toBe('wake-up');
});

it('completes an active alarm without cancelling its schedule', function () {
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

    (new NativePHPAlarmGateway(new AlarmScheduler($bridge)))->complete('weekday-wake-up');

    expect($bridge->methods)->toBe(['Alarms.Complete']);
});

it('uses the plugin snooze bridge for an active alarm', function () {
    $bridge = new class implements NativeAlarmBridge
    {
        /** @var array<string, mixed> */
        public array $parameters = [];

        public function call(string $method, array $parameters = []): array
        {
            $this->parameters = $parameters;

            return [];
        }
    };

    (new NativePHPAlarmGateway(new AlarmScheduler($bridge)))->snooze('wake-up', 5);

    expect($bridge->parameters)->toBe(['id' => 'wake-up', 'minutes' => 5]);
});
