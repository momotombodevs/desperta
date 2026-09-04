<?php

use App\AlarmScheduling\ActiveAlarmOccurrence;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Models\Alarm;
use App\Models\AlarmExecution;
use App\NativeComponents\Challenge;
use App\NativeComponents\Home;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

it('automatically resumes the ringing occurrence when home opens', function () {
    $alarm = Alarm::factory()->create();
    $occurrence = new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T06:30:00+00:00');
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn($occurrence);

    Native::test(Home::class)
        ->assertSee('Sonando · Continuar reto')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === "edit-alarm-{$alarm->id}"
            && ($node['props']['container_color'] ?? null) === '#B45309')
        ->assertAccessible()
        ->assertReplacedWith('/challenge')
        ->follow()
        ->assertScreen(Challenge::class)
        ->assertSet('alarmId', $alarm->id)
        ->assertSet('executionId', 'execution-1');
});

it('automatically opens a ringing challenge when home resumes', function () {
    $alarm = Alarm::factory()->create();
    $occurrence = new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T06:30:00+00:00');
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')->twice()->andReturn(null, $occurrence);

    Native::test(Home::class)->assertNoNavigation()->call('onResume')->assertReplacedWith('/challenge');
});

it('rechecks an outdated ringing indicator before opening its row', function () {
    $alarm = Alarm::factory()->create();
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->twice()->andReturnNull();

    Native::test(Home::class)
        ->set('activeAlarmId', $alarm->id)
        ->tap("edit-alarm-{$alarm->id}")
        ->assertNavigatedTo("/alarms/{$alarm->id}/edit")
        ->assertDontSee('Sonando · Continuar reto');
});

it('does not resume a terminal execution reported by a stale native occurrence', function (string $status) {
    $alarm = Alarm::factory()->create();
    $execution = AlarmExecution::factory()->for($alarm)->create(['status' => $status]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->twice()->andReturn(
        new ActiveAlarmOccurrence($alarm->id, $execution->id, $execution->scheduled_for->toIso8601String()),
    );

    Native::test(Home::class)
        ->assertDontSee('Sonando · Continuar reto')
        ->tap("edit-alarm-{$alarm->id}")
        ->assertNavigatedTo("/alarms/{$alarm->id}/edit");
})->with(['completed', 'cancelled', 'missed']);

it('does not highlight an execution belonging to a different alarm', function () {
    $alarm = Alarm::factory()->create();
    $execution = AlarmExecution::factory()->create(['status' => 'ringing']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->once()->andReturn(
        new ActiveAlarmOccurrence($alarm->id, $execution->id, $execution->scheduled_for->toIso8601String()),
    );

    Native::test(Home::class)
        ->assertDontSee('Sonando · Continuar reto')
        ->assertNoNavigation();
});

it('ignores native occurrences whose alarm no longer exists', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->once()->andReturn(
        new ActiveAlarmOccurrence('deleted-alarm', 'execution-1', '2026-09-03T06:30:00+00:00'),
    );

    Native::test(Home::class)
        ->assertSet('activeAlarmId', '')
        ->assertNoNavigation();
});

it('removes the ringing indicator when its execution completes while home is away', function () {
    $alarm = Alarm::factory()->create();
    $execution = AlarmExecution::factory()->for($alarm)->create(['status' => 'ringing']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->twice()->andReturn(
        null,
        new ActiveAlarmOccurrence($alarm->id, $execution->id, $execution->scheduled_for->toIso8601String()),
    );
    $home = Native::test(Home::class)->set('activeAlarmId', $alarm->id)->assertSee('Sonando · Continuar reto');
    $execution->update(['status' => 'completed']);

    $home->call('onResume')->assertDontSee('Sonando · Continuar reto');
});

it('renders only alarms created by the user with a trailing activation switch', function () {
    $alarm = Alarm::factory()->create(['time' => '07:15', 'label' => 'Universidad']);

    Native::visit('/')
        ->assertSee('PRÓXIMA ALARMA')
        ->assertSee('Tus alarmas')
        ->assertSee('7:15 a. m.')
        ->assertSee('Universidad')
        ->assertDontSee('ACTIVA')
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
    $scheduler->shouldReceive('activeRingingOccurrence')->once()->andReturnNull();
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
    $scheduler->shouldReceive('activeRingingOccurrence')->once()->andReturnNull();
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
    $scheduler->shouldReceive('activeRingingOccurrence')->once()->andReturnNull();
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
