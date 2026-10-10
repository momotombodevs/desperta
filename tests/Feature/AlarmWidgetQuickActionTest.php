<?php

use App\Application\AlarmScheduling\AlarmSchedule;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Models\Alarm;
use App\Models\AlarmExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Momotombo\NativePHPAlarms\Exceptions\NativeAlarmSchedulingFailed;
use Native\Mobile\Testing\Native;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

it('pauses the selected alarm in SQLite and Android when its widget action is activated', function () {
    $alarm = Alarm::factory()->create(['enabled' => true, 'scheduling_status' => 'scheduled']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('cancel')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::visit("/quick-actions/alarms/{$alarm->id}/toggle")
        ->assertReplacedWith('/');

    expect($alarm->fresh()->enabled)->toBeFalse()
        ->and($alarm->fresh()->scheduling_status)->toBe('not_scheduled');
});

it('activates the selected alarm from the widget when required permissions are available', function () {
    $alarm = Alarm::factory()->create(['enabled' => false, 'scheduling_status' => 'not_scheduled']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('canScheduleExactly')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPresentWhileLocked')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPostNotifications')->once()->andReturnTrue();
    $scheduler->shouldReceive('schedule')->once()->withArgs(fn (AlarmSchedule $schedule): bool => $schedule->id === $alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::visit("/quick-actions/alarms/{$alarm->id}/toggle")
        ->assertReplacedWith('/');

    expect($alarm->fresh()->enabled)->toBeTrue()
        ->and($alarm->fresh()->scheduling_status)->toBe('scheduled')
        ->and(AlarmExecution::query()->where('alarm_id', $alarm->id)->where('status', 'scheduled')->exists())->toBeTrue();
});

it('reports native bridge errors while checking widget alarm capabilities', function () {
    $alarm = Alarm::factory()->create(['enabled' => false, 'scheduling_status' => 'not_scheduled']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('canScheduleExactly')->once()->andThrow(new NativeAlarmSchedulingFailed('Bridge unavailable.'));
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::visit("/quick-actions/alarms/{$alarm->id}/toggle")
        ->assertToastShownWithMessage(__('app.widget_alarm_error'))
        ->assertReplacedWith('/');

    expect($alarm->fresh()->enabled)->toBeFalse()
        ->and($alarm->fresh()->scheduling_status)->toBe('not_scheduled');
});
