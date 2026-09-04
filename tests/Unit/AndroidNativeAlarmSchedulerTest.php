<?php

use App\AlarmScheduling\ActiveAlarmOccurrence;
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
        executionId: '018f0b8d-1d3e-7f14-8caa-111111111111',
        scheduledFor: '2026-09-03T06:30:00+00:00',
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

it('passes snooze duration to the native gateway', function () {
    $alarms = mock(NativeAlarmGateway::class);
    $alarms->shouldReceive('snooze')->once()->with('wake-up', 5);

    (new AndroidNativeAlarmScheduler($alarms))->snooze('wake-up', 5);
});

it('returns the active native ringing occurrence', function () {
    $alarms = mock(NativeAlarmGateway::class);
    $occurrence = new ActiveAlarmOccurrence('wake-up', 'execution-1', '2026-09-03T06:30:00+00:00');
    $alarms->shouldReceive('activeRingingOccurrence')->once()->andReturn($occurrence);

    expect((new AndroidNativeAlarmScheduler($alarms))->activeRingingOccurrence())->toBe($occurrence);
});
