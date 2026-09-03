<?php

use App\Application\AlarmScheduling\AlarmSchedule;
use App\Application\AlarmScheduling\NativeAlarmGateway;
use App\Infrastructure\NativeAlarm\AndroidNativeAlarmScheduler;

use function Pest\Laravel\mock;

it('passes the complete alarm schedule to the native gateway', function () {
    $alarm = new AlarmSchedule(
        id: 'wake-up',
        time: '06:30',
        label: 'Trabajo',
        weekdays: [5],
        vibration: true,
        snoozeEnabled: false,
        difficulty: 'Normal',
    );

    $alarms = mock(NativeAlarmGateway::class);
    $alarms->shouldReceive('schedule')
        ->once()
        ->with($alarm);

    (new AndroidNativeAlarmScheduler($alarms))->schedule($alarm);
});

it('completes only the active native ringing session', function () {
    $alarms = mock(NativeAlarmGateway::class);
    $alarms->shouldReceive('complete')->once()->with('wake-up');
    $alarms->shouldNotReceive('cancel');

    (new AndroidNativeAlarmScheduler($alarms))->completeRinging('wake-up');
});

it('returns the active native ringing alarm id', function () {
    $alarms = mock(NativeAlarmGateway::class);
    $alarms->shouldReceive('activeRingingAlarmId')->once()->andReturn('wake-up');

    expect((new AndroidNativeAlarmScheduler($alarms))->activeRingingAlarmId())->toBe('wake-up');
});
