<?php

use App\AlarmScheduling\NativeAlarmOccurrenceEvent;
use App\Application\AlarmScheduling\NativeAlarmGateway;
use App\Application\Preferences\AppPreferences;
use App\Application\Preferences\BedtimeReminderSettings;
use App\Models\Alarm;
use App\Models\AlarmExecution;
use App\Models\AlarmExecutionRoutineStep;
use App\NativeComponents\Habits;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Native\Mobile\Testing\Native;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    app(AppPreferences::class)->setLanguage('es_NI');
});

it('renders punctuality metrics for terminal executions in the selected period', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));

    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-02 07:00:00', 'America/Managua')->utc(),
        'finished_at' => CarbonImmutable::parse('2026-09-02 07:05:00', 'America/Managua')->utc(),
    ]);
    AlarmExecution::factory()->create([
        'status' => 'missed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-03 07:00:00', 'America/Managua')->utc(),
    ]);

    Native::visit('/settings/habits')
        ->assertSee('50%')
        ->assertSee('A tiempo · 7 días: 1/2')
        ->assertDontSee('La racha suma días seguidos')
        ->assertDontSee('Una alarma cuenta a tiempo')
        ->assertDontSee('Cada día cuenta una vez')
        ->assertDontSee('Los gráficos muestran')
        ->assertDontSee('Estás atendiendo tus alarmas a tiempo')
        ->assertSee('Mejor racha')
        ->assertSee('Sin posponer')
        ->assertSee('ÚLTIMOS 7 DÍAS')
        ->assertSee('Resultados diarios')
        ->assertSee('Distribución de resultados')
        ->assertElement('bar_chart', function (array $node): bool {
            $series = json_decode($node['props']['series_json'] ?? '[]', true);

            return array_column($series, 'id') === ['on_time', 'late', 'missed']
                && array_sum(array_column($series[0]['points'], 'value')) === 1
                && array_sum(array_column($series[1]['points'], 'value')) === 0
                && array_sum(array_column($series[2]['points'], 'value')) === 1;
        })
        ->assertElement('donut_chart', function (array $node): bool {
            $segments = json_decode($node['props']['segments_json'] ?? '[]', true);

            return array_column($segments, 'id') === ['on_time', 'late', 'missed']
                && array_column($segments, 'value') === [1, 0, 1];
        })
        ->assertElement('bar_chart', function (array $node): bool {
            $axis = json_decode($node['props']['y_axis_json'] ?? '{}', true);

            return ($axis['minimum'] ?? null) == 0 && ($axis['maximum'] ?? null) == 2 && ($axis['interval'] ?? null) == 1;
        });
});

it('reconciles a completed native wake-up before rendering its habits', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-04 02:00:00', 'UTC'));
    $alarm = Alarm::factory()->create(['time' => '19:40']);
    $event = new NativeAlarmOccurrenceEvent(
        alarmId: $alarm->id,
        executionId: '99ee0b3f-d052-4298-8675-b1b78409fed7',
        scheduledFor: '2026-09-04T19:40:00+00:00',
        status: 'completed',
        occurredAt: '2026-09-04T01:42:32+00:00',
    );
    AlarmExecution::factory()->for($alarm)->create([
        'id' => $event->executionId,
        'status' => 'scheduled',
        'scheduled_for' => $event->scheduledFor,
        'started_at' => null,
        'finished_at' => null,
    ]);
    $gateway = mock(NativeAlarmGateway::class);
    $gateway->shouldReceive('occurrenceEvents')->once()->andReturn([$event]);
    $gateway->shouldReceive('acknowledgeOccurrenceEvents')->once()->with([$event->executionId]);
    app()->instance(NativeAlarmGateway::class, $gateway);

    Native::visit('/settings/habits')
        ->assertSee('A tiempo · 7 días: 1/1')
        ->assertDontSee('Todavía no hay hábitos que mostrar');

    $this->assertDatabaseHas('alarm_executions', [
        'id' => $event->executionId,
        'status' => 'completed',
        'scheduled_for' => '2026-09-04 01:40:00',
        'finished_at' => '2026-09-04 01:42:32',
    ]);
});

it('renders the localized empty state when the period has no terminal executions', function () {
    Native::visit('/settings/habits')
        ->assertSee('Todavía no hay hábitos que mostrar')
        ->assertDontSee('Mejor racha')
        ->assertMissingElement('bar_chart')
        ->assertMissingElement('donut_chart');
});

it('uses English habit labels when English is selected', function () {
    app(AppPreferences::class)->setLanguage('en');

    Native::visit('/settings/habits')
        ->assertSee('Habits')
        ->assertSee('No habits to show yet');
});

it('applies the saved Spanish locale when habits are opened with the default app locale', function () {
    app(AppPreferences::class)->setLanguage('es_NI');
    app()->setLocale('en');

    Native::visit('/settings/habits')
        ->assertSee('Hábitos')
        ->assertSee('Todavía no hay hábitos que mostrar')
        ->assertDontSee('Habits')
        ->assertDontSee('No habits to show yet');
});

it('keeps habit metrics concise in English when there is activity', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));
    app(AppPreferences::class)->setLanguage('en');
    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-02 07:00:00', 'America/Managua')->utc(),
        'finished_at' => CarbonImmutable::parse('2026-09-02 07:05:00', 'America/Managua')->utc(),
    ]);

    Native::visit('/settings/habits')
        ->assertSee('On time · 7 days: 1/1')
        ->assertDontSee('A streak counts consecutive days')
        ->assertDontSee('An alarm counts as on time')
        ->assertDontSee('Percentage of completed alarms with no snoozes');
});

it('renders localized weekday labels and singular streaks in Spanish', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-04 18:00:00', 'UTC'));
    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-04 07:00:00', 'America/Managua')->utc(),
        'finished_at' => CarbonImmutable::parse('2026-09-04 07:05:00', 'America/Managua')->utc(),
    ]);

    Native::visit('/settings/habits')
        ->assertSee('1 día')
        ->assertDontSee('1 días')
        ->assertSee('Sá')
        ->assertSee('Do')
        ->assertSee('Lu')
        ->assertSee('Ma')
        ->assertSee('Mi')
        ->assertSee('Ju')
        ->assertSee('Vi')
        ->assertElement('bar_chart', function (array $node): bool {
            $series = json_decode($node['props']['series_json'] ?? '[]', true);

            return array_column($series[0]['points'], 'label') === ['Sá', 'Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi'];
        });
});

it('persists a valid weekly goal and renders the localized weekly summary', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));
    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-02 07:00:00', 'America/Managua')->utc(),
        'finished_at' => CarbonImmutable::parse('2026-09-02 07:05:00', 'America/Managua')->utc(),
    ]);

    Native::test(Habits::class)
        ->call('selectWeeklyGoal', '1')
        ->assertSet('weeklyGoalSelection', '1')
        ->assertSee('1 día')
        ->call('selectWeeklyGoal', '6')
        ->assertSet('weeklyGoalSelection', '6')
        ->assertSee('Meta semanal')
        ->assertSee('Esta semana')
        ->assertSee('1 de 6 mañanas a tiempo esta semana');

    expect(app(AppPreferences::class)->weeklyGoal())->toBe(6);
});

it('labels the historical hardest weekday as a recent-weeks result in both languages', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));

    foreach (['2026-08-17', '2026-08-24'] as $date) {
        AlarmExecution::factory()->create([
            'status' => 'missed',
            'scheduled_for' => CarbonImmutable::parse("{$date} 07:00:00", 'America/Managua')->utc(),
        ]);
    }

    app()->setLocale('en');
    app(AppPreferences::class)->setLanguage('es_NI');
    Native::visit('/settings/habits')
        ->assertSee('Día más difícil en las últimas semanas: Lunes');

    app(AppPreferences::class)->setLanguage('en');
    Native::visit('/settings/habits')
        ->assertSee('Most difficult day in recent weeks: Monday');
});

it('shows the streak protector, the five-week calendar and the routine completion', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));
    foreach (['2026-08-31' => 4, '2026-09-01' => 30, '2026-09-02' => 5] as $date => $minutesLate) {
        $scheduledFor = CarbonImmutable::parse("{$date} 07:00:00", 'America/Managua')->utc();
        $execution = AlarmExecution::factory()->create([
            'status' => 'completed',
            'scheduled_for' => $scheduledFor,
            'finished_at' => $scheduledFor->addMinutes($minutesLate),
        ]);
        AlarmExecutionRoutineStep::query()->create([
            'alarm_execution_id' => $execution->id, 'label' => 'Agua', 'position' => 0,
            'completed_at' => $minutesLate < 10 ? $scheduledFor->addMinutes(20) : null,
        ]);
    }

    Native::visit('/settings/habits')
        ->assertSee('2 días')
        ->assertSee('Protector de racha recargándose')
        ->assertSee('Septiembre 2026')
        ->assertElement('column', fn (array $node): bool => ($node['a11y_label'] ?? $node['props']['a11y_label'] ?? null) === '1: Día salvado por el protector')
        ->assertSee('2 de 3 rutinas completas · 7 días')
        ->assertAccessible();
});

it('shows how fast alarms are turned off and the sleep window before the next alarm', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 18:00:00', 'UTC'));
    app(BedtimeReminderSettings::class)->save(true, '22:00', [1, 2, 3, 4, 5]);
    Alarm::factory()->create(['time' => '06:30', 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'enabled' => true]);
    foreach (['2026-09-07', '2026-09-08', '2026-09-09'] as $date) {
        $scheduledFor = CarbonImmutable::parse("{$date} 06:00:00", 'America/Managua')->utc();
        AlarmExecution::factory()->create([
            'status' => 'completed',
            'scheduled_for' => $scheduledFor,
            'finished_at' => $scheduledFor->addMinutes(30),
        ]);
    }

    Native::visit('/settings/habits')
        ->assertSee('Regularidad al despertar')
        ->assertSee('Variación: ±0 min')
        ->assertSee('Apagás la alarma en promedio 30 min después de que suena')
        ->assertSee('Entre tu recordatorio de las 10:00 p. m. y tu próxima alarma de las 6:30 a. m. tenés 8 h 30 min para dormir');
});

it('hides the sleep window when the next alarm does not leave a plausible night', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 18:00:00', 'UTC'));
    app(BedtimeReminderSettings::class)->save(true, '21:30', [1, 2, 3, 4, 5, 6, 7]);
    Alarm::factory()->create(['time' => '21:00', 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'enabled' => true]);
    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-09 21:00:00', 'America/Managua')->utc(),
        'finished_at' => CarbonImmutable::parse('2026-09-09 21:02:00', 'America/Managua')->utc(),
    ]);

    Native::visit('/settings/habits')
        ->assertDontSee('para dormir')
        ->assertDontSee('Dormís en promedio')
        ->assertDontSee('Regularidad al despertar');
});

it('browses the calendar month by month without going past the current month', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));
    foreach (['2026-08-12', '2026-09-02'] as $date) {
        AlarmExecution::factory()->create([
            'status' => 'completed',
            'scheduled_for' => CarbonImmutable::parse("{$date} 07:00:00", 'America/Managua')->utc(),
            'finished_at' => CarbonImmutable::parse("{$date} 07:03:00", 'America/Managua')->utc(),
        ]);
    }

    Native::visit('/settings/habits')
        ->assertSee('Septiembre 2026')
        ->tap('calendar-next')
        ->assertSee('Septiembre 2026')
        ->tap('calendar-previous')
        ->assertSet('calendarMonth', '2026-08')
        ->assertSee('Agosto 2026')
        ->assertElement('column', fn (array $node): bool => ($node['a11y_label'] ?? $node['props']['a11y_label'] ?? null) === '12: Despertaste a tiempo');
});

it('shows the average real sleep between the bedtime reminder and actual wake-ups', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 18:00:00', 'UTC'));
    app(BedtimeReminderSettings::class)->save(true, '22:00', [1, 2, 3, 4, 5, 6, 7]);
    foreach (['2026-09-07 06:00', '2026-09-08 06:30', '2026-09-09 07:00'] as $wakeUp) {
        $finishedAt = CarbonImmutable::parse($wakeUp, 'America/Managua');
        AlarmExecution::factory()->create([
            'status' => 'completed',
            'scheduled_for' => $finishedAt->subMinutes(2)->utc(),
            'finished_at' => $finishedAt->utc(),
        ]);
    }

    Native::visit('/settings/habits')
        ->assertSee('Dormís en promedio 8 h 30 min, según tu recordatorio y tus despertares reales (3 noches)');
});
