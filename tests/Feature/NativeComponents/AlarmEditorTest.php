<?php

use App\Application\AlarmScheduling\AlarmSchedule;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Models\Alarm;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Momotombo\NativePHPAlarms\Events\NotificationAuthorizationChanged;
use Momotombo\NativePHPAlarms\Exceptions\NativeAlarmSchedulingFailed;
use Native\Mobile\Testing\Native;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

it('persists every selected alarm setting without scheduling a disabled alarm', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldNotReceive('canScheduleExactly');
    $scheduler->shouldNotReceive('schedule');
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::visit('/alarms/new')
        ->assertElement('date_picker', fn (array $node): bool => ($node['props']['hour_format'] ?? null) === '12'
            && ! array_key_exists('locale', $node['props'] ?? []))
        ->pickTime('time', '06:45')
        ->set('label', 'Salir a correr')
        ->set('monday', false)
        ->set('saturday', true)
        ->set('sunday', true)
        ->assertSee('Alarma activada')
        ->toggle('enabled', false)
        ->select('difficultyDisplay', 'Difícil')
        ->tap('save-alarm')
        ->assertReplacedWith('/');

    $this->assertDatabaseHas('alarms', [
        'time' => '06:45',
        'label' => 'Salir a correr',
        'weekdays' => json_encode([2, 3, 4, 5, 6, 7]),
        'difficulty' => 'Difícil',
        'enabled' => false,
        'scheduling_status' => 'not_scheduled',
    ]);
});

it('sends enabled alarms through the scheduling boundary after persisting them', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('canScheduleExactly')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPresentWhileLocked')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPostNotifications')->once()->andReturnTrue();
    $scheduler->shouldReceive('schedule')->once()->withArgs(function (AlarmSchedule $schedule): bool {
        return $schedule->time === '07:15'
            && $schedule->weekdays === [1, 2, 3, 4, 5]
            && $schedule->difficulty === 'Fácil';
    });
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::visit('/alarms/new')
        ->pickTime('time', '07:15')
        ->set('difficulty', 'Fácil')
        ->tap('save-alarm')
        ->assertToastShownWithMessage('Alarma programada.')
        ->assertReplacedWith('/');

    $this->assertDatabaseHas('alarms', [
        'time' => '07:15',
        'weekdays' => json_encode([1, 2, 3, 4, 5]),
        'difficulty' => 'Fácil',
        'enabled' => true,
        'scheduling_status' => 'scheduled',
    ]);

    expect(Alarm::query()->sole()->enabled)->toBeTrue();
});

it('continues a pending alarm after returning from Android exact alarm settings', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('canScheduleExactly')->times(3)->andReturn(false, true, true);
    $scheduler->shouldReceive('requestExactAlarmPermission')->once();
    $scheduler->shouldReceive('canPresentWhileLocked')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPostNotifications')->once()->andReturnTrue();
    $scheduler->shouldReceive('schedule')->once();
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $editor = Native::visit('/alarms/new')
        ->pickTime('time', '06:30')
        ->set('label', 'Trabajo')
        ->tap('save-alarm')
        ->assertSet('resumeAfterExactAlarmPermission', true);

    $this->assertDatabaseHas('alarms', [
        'time' => '06:30',
        'label' => 'Trabajo',
        'enabled' => true,
        'scheduling_status' => 'pending',
    ]);

    $editor->call('onResume')
        ->assertToastShownWithMessage('Alarma programada.')
        ->assertReplacedWith('/');

    expect(Alarm::query()->count())->toBe(1);
    expect(Alarm::query()->sole()->scheduling_status)->toBe('scheduled');
});

it('continues a pending alarm after returning from Android full-screen alarm settings', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('canScheduleExactly')->times(2)->andReturnTrue();
    $scheduler->shouldReceive('canPresentWhileLocked')->times(3)->andReturn(false, true, true);
    $scheduler->shouldReceive('requestFullScreenAlarmPermission')->once();
    $scheduler->shouldReceive('canPostNotifications')->once()->andReturnTrue();
    $scheduler->shouldReceive('schedule')->once();
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $editor = Native::visit('/alarms/new')
        ->set('label', 'Trabajo')
        ->tap('save-alarm')
        ->assertSet('resumeAfterFullScreenAlarmPermission', true);

    $this->assertDatabaseHas('alarms', [
        'label' => 'Trabajo',
        'enabled' => true,
        'scheduling_status' => 'pending',
    ]);

    $editor->call('onResume')
        ->assertToastShownWithMessage('Alarma programada.')
        ->assertReplacedWith('/');

    expect(Alarm::query()->sole()->scheduling_status)->toBe('scheduled');
});

it('schedules a pending alarm after Android grants notification permission', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('canScheduleExactly')->twice()->andReturnTrue();
    $scheduler->shouldReceive('canPresentWhileLocked')->twice()->andReturnTrue();
    $scheduler->shouldReceive('canPostNotifications')->twice()->andReturn(false, true);
    $scheduler->shouldReceive('requestNotificationPermission')->once()->withArgs(fn (string $requestId): bool => $requestId !== '');
    $scheduler->shouldReceive('schedule')->once();
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $editor = Native::visit('/alarms/new')
        ->set('label', 'Lectura')
        ->tap('save-alarm')
        ->assertSet('awaitingPermission', true);

    $this->assertDatabaseHas('alarms', [
        'label' => 'Lectura',
        'enabled' => true,
        'scheduling_status' => 'pending',
    ]);

    $editor->emitNative(NotificationAuthorizationChanged::class, [
        'granted' => true,
        'requestId' => $editor->get('notificationPermissionRequestId'),
    ])
        ->assertToastShownWithMessage('Alarma programada.')
        ->assertReplacedWith('/');

    expect(Alarm::query()->sole()->scheduling_status)->toBe('scheduled');
});

it('keeps a pending alarm in the editor when Android denies notification permission', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('canScheduleExactly')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPresentWhileLocked')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPostNotifications')->once()->andReturnFalse();
    $scheduler->shouldReceive('requestNotificationPermission')->once()->withArgs(fn (string $requestId): bool => $requestId !== '');
    $scheduler->shouldNotReceive('schedule');
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $editor = Native::visit('/alarms/new')
        ->set('label', 'Lectura')
        ->tap('save-alarm');

    $editor->emitNative(NotificationAuthorizationChanged::class, [
        'granted' => false,
        'requestId' => $editor->get('notificationPermissionRequestId'),
    ])
        ->assertSet('awaitingPermission', false)
        ->assertToastShownWithMessage('No se pudieron activar las notificaciones.')
        ->assertDontSee('Permití las notificaciones')
        ->assertDontSee('No se pudo programar');

    $this->assertDatabaseHas('alarms', [
        'label' => 'Lectura',
        'enabled' => true,
        'scheduling_status' => 'pending',
    ]);
});

it('keeps a pending alarm and reports the native scheduling failure', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('canScheduleExactly')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPresentWhileLocked')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPostNotifications')->once()->andReturnTrue();
    $scheduler->shouldReceive('schedule')->once()->andThrow(new NativeAlarmSchedulingFailed('Android rejected this exact alarm.'));
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::visit('/alarms/new')
        ->tap('save-alarm')
        ->assertToastShownWithMessage('Android rejected this exact alarm.')
        ->assertDontSee('No se pudo programar');

    $this->assertDatabaseHas('alarms', [
        'enabled' => true,
        'scheduling_status' => 'pending',
    ]);
});

it('updates and reschedules an existing alarm instead of creating a duplicate', function () {
    $alarm = Alarm::factory()->create([
        'time' => '07:00',
        'label' => 'Anterior',
        'scheduling_status' => 'scheduled',
    ]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('canScheduleExactly')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPresentWhileLocked')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPostNotifications')->once()->andReturnTrue();
    $scheduler->shouldReceive('cancel')->once()->with($alarm->id);
    $scheduler->shouldReceive('schedule')->once()->withArgs(function (AlarmSchedule $schedule) use ($alarm): bool {
        return $schedule->id === $alarm->id && $schedule->time === '08:20' && $schedule->label === 'Actualizada';
    });
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::visit("/alarms/{$alarm->id}/edit")
        ->assertSee('Editar alarma')
        ->assertSee('Cancelar')
        ->assertSee('Guardar')
        ->pickTime('time', '08:20')
        ->set('label', 'Actualizada')
        ->tap('save-alarm')
        ->assertReplacedWith('/');

    expect(Alarm::query()->count())->toBe(1);
    expect($alarm->fresh())
        ->time->toBe('08:20')
        ->label->toBe('Actualizada')
        ->scheduling_status->toBe('scheduled');
});

it('discards editor changes when cancelling an existing alarm', function () {
    $alarm = Alarm::factory()->create(['label' => 'Sin cambios']);

    Native::visit("/alarms/{$alarm->id}/edit")
        ->set('label', 'No guardar')
        ->tap('cancel-alarm')
        ->assertReplacedWith('/');

    $this->assertDatabaseHas('alarms', [
        'id' => $alarm->id,
        'label' => 'Sin cambios',
    ]);
});
