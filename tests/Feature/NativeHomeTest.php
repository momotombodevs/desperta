<?php

use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Application\Preferences\AppPreferences;
use App\Models\Alarm;
use App\Models\AlarmExecution;
use App\NativeComponents\Challenge;
use App\NativeComponents\Home;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

it('reopens the challenge with the active alarm id when an alarm is still ringing', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingAlarmId')->once()->andReturn('wake-up');
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::test(Home::class)
        ->assertReplacedWith('/challenge')
        ->follow()
        ->assertScreen(Challenge::class)
        ->assertSet('alarmId', 'wake-up');
});

it('renders only alarms created by the user with a trailing activation switch', function () {
    $alarm = Alarm::factory()->create(['time' => '07:15', 'label' => 'Universidad']);

    Native::visit('/')
        ->assertSee('PRÓXIMA ALARMA')
        ->assertSee('Tus alarmas')
        ->assertSee('7:15 a. m.')
        ->assertSee('Universidad')
        ->assertDontSee('ACTIVA')
        ->assertDontSee('AlarmHistorySheet')
        ->assertDontSee('SettingsSheet')
        ->assertElement('list', fn (array $node): bool => ($node['ref'] ?? null) === 'alarm-list'
            && collect($node['children'] ?? [])->contains(
                fn (array $child): bool => ($child['type'] ?? null) === 'list_item'
                    && ($child['ref'] ?? null) === "edit-alarm-{$alarm->id}",
            ))
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === "edit-alarm-{$alarm->id}"
            && ($node['style']['border_width'] ?? null) === 1.0
            && ($node['props']['trailing_type'] ?? null) === 'switch'
            && ($node['props']['trailing_checked'] ?? null) === true)
        ->assertAccessible();
});

it('cancels a scheduled alarm from its activation switch', function () {
    $alarm = Alarm::factory()->create(['scheduling_status' => 'scheduled']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingAlarmId')->once()->andReturnNull();
    $scheduler->shouldReceive('cancel')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::visit('/')
        ->call('toggleAlarm', $alarm->id, false);

    $this->assertDatabaseHas('alarms', [
        'id' => $alarm->id,
        'enabled' => false,
        'scheduling_status' => 'not_scheduled',
    ]);
});

it('schedules a disabled alarm from its activation switch', function () {
    $alarm = Alarm::factory()->create([
        'enabled' => false,
        'scheduling_status' => 'not_scheduled',
    ]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingAlarmId')->once()->andReturnNull();
    $scheduler->shouldReceive('canScheduleExactly')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPresentWhileLocked')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPostNotifications')->once()->andReturnTrue();
    $scheduler->shouldReceive('schedule')->once()->withArgs(fn ($schedule): bool => $schedule->id === $alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::visit('/')
        ->call('toggleAlarm', $alarm->id, true)
        ->assertToastShownWithMessage('Alarma programada.');

    $this->assertDatabaseHas('alarms', [
        'id' => $alarm->id,
        'enabled' => true,
        'scheduling_status' => 'scheduled',
    ]);
});

it('navigates to the editor for an existing alarm', function () {
    $alarm = Alarm::factory()->create(['time' => '07:15']);

    Native::visit('/')
        ->tap("edit-alarm-{$alarm->id}")
        ->assertNavigatedTo("/alarms/{$alarm->id}/edit")
        ->follow()
        ->assertSee('Editar alarma');
});

it('deletes a scheduled alarm only after confirming its native swipe action', function () {
    $alarm = Alarm::factory()->create([
        'label' => 'Universidad',
        'scheduling_status' => 'scheduled',
    ]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingAlarmId')->once()->andReturnNull();
    $scheduler->shouldReceive('cancel')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $home = Native::visit('/')
        ->call('confirmDeleteAlarm', $alarm->id)
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['title'] === '¿Eliminar alarma?'
            && $params['buttons'][1] === ['label' => 'Eliminar', 'style' => 'destructive']);

    $this->assertDatabaseHas('alarms', ['id' => $alarm->id]);

    $home->emitNative(ButtonPressed::class, [
        'index' => 1,
        'label' => 'Eliminar',
        'id' => "delete-alarm-{$alarm->id}",
    ])
        ->assertDontSee('Universidad');

    $this->assertDatabaseMissing('alarms', ['id' => $alarm->id]);
});

it('keeps an alarm when the native deletion dialog is cancelled', function () {
    $alarm = Alarm::factory()->create(['label' => 'Universidad']);

    Native::visit('/')
        ->call('confirmDeleteAlarm', $alarm->id)
        ->emitNative(ButtonPressed::class, [
            'index' => 0,
            'label' => 'Cancelar',
            'id' => "delete-alarm-{$alarm->id}",
        ])
        ->assertSee('Universidad');

    $this->assertDatabaseHas('alarms', ['id' => $alarm->id]);
});

it('opens the five most recent localized executions from settings', function () {
    app(AppPreferences::class)->setLanguage('en');
    $alarm = Alarm::factory()->create();

    foreach (range(1, 6) as $position) {
        AlarmExecution::factory()->for($alarm)->create([
            'alarm_label' => "Execution {$position}",
            'scheduled_for' => now()->addMinutes($position),
            'status' => 'completed',
        ]);
    }

    Native::visit('/')
        ->assertElement('list', fn (array $node): bool => ($node['ref'] ?? null) === 'alarm-list'
            && ! collect($node['children'] ?? [])->contains(
                fn (array $child): bool => ($child['type'] ?? null) === 'list_item'
                    && ($child['props']['headline'] ?? null) === 'Execution 6',
            ))
        ->tap('settings')
        ->tap('open-history')
        ->assertSet('settingsOpen', false)
        ->assertSet('historyOpen', true)
        ->assertSee('Completed')
        ->assertSee('Execution 6')
        ->assertDontSee('Execution 1')
        ->call('closeHistory')
        ->assertSet('historyOpen', false);
});
