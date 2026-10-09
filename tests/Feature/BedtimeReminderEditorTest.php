<?php

use App\Application\Preferences\BedtimeReminderSettings;
use App\Models\AppPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Momotombo\NativePHPAlarms\Bridge\NativeAlarmBridge;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

it('schedules an enabled bedtime reminder with selected weekdays and stores local settings', function () {
    $bridge = Mockery::mock(NativeAlarmBridge::class);
    $bridge->shouldReceive('call')->with('Alarms.Capabilities', [])->once()->andReturn(['exact' => true]);
    $bridge->shouldReceive('call')->with('Alarms.AuthorizationStatus', [])->once()->andReturn(['status' => 'authorized']);
    $bridge->shouldReceive('call')->with('Alarms.NotificationAuthorizationStatus', [])->once()->andReturn(['status' => 'authorized']);
    $bridge->shouldReceive('call')->withArgs(fn (string $method, array $parameters): bool => $method === 'Alarms.ScheduleBedtimeReminder'
        && $parameters === ['hour' => 22, 'minute' => 15, 'weekdays' => ['monday', 'friday'], 'title' => 'Prepará tu descanso', 'body' => 'Revisá la alarma que te espera mañana.'])->once()->andReturn(['status' => 'success']);
    app()->instance(NativeAlarmBridge::class, $bridge);

    Native::visit('/settings/bedtime-reminder')
        ->toggle('enabled', true)
        ->pickTime('time', '22:15')
        ->set('tuesday', false)
        ->set('wednesday', false)
        ->set('thursday', false)
        ->set('saturday', false)
        ->set('sunday', false)
        ->tap('save-bedtime-reminder')
        ->assertToastShownWithMessage('Recordatorio actualizado.');

    expect(json_decode(AppPreference::query()->where('key', 'bedtime_reminder')->value('value'), true))->toBe([
        'enabled' => true,
        'time' => '22:15',
        'weekdays' => [1, 5],
    ]);
});

it('cancels the native reminder when it is disabled', function () {
    app(BedtimeReminderSettings::class)->save(true, '21:00', [1, 2, 3, 4, 5]);
    $bridge = Mockery::mock(NativeAlarmBridge::class);
    $bridge->shouldReceive('call')->with('Alarms.CancelBedtimeReminder', [])->once()->andReturn(['status' => 'success']);
    app()->instance(NativeAlarmBridge::class, $bridge);

    Native::visit('/settings/bedtime-reminder')
        ->toggle('enabled', false)
        ->tap('save-bedtime-reminder')
        ->assertToastShownWithMessage('Recordatorio actualizado.');

    expect(app(BedtimeReminderSettings::class)->get()['enabled'])->toBeFalse();
});

it('does not schedule a reminder without at least one selected day', function () {
    $bridge = Mockery::mock(NativeAlarmBridge::class);
    $bridge->shouldNotReceive('call');
    app()->instance(NativeAlarmBridge::class, $bridge);

    Native::visit('/settings/bedtime-reminder')
        ->toggle('enabled', true)
        ->set('monday', false)
        ->set('tuesday', false)
        ->set('wednesday', false)
        ->set('thursday', false)
        ->set('friday', false)
        ->set('saturday', false)
        ->set('sunday', false)
        ->tap('save-bedtime-reminder')
        ->assertToastShownWithMessage('Elegí una hora y al menos un día.');

    $this->assertDatabaseMissing('app_preferences', ['key' => 'bedtime_reminder']);
});
