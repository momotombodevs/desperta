<?php

use App\AlarmScheduling\NativeAlarmOccurrenceEvent;
use App\Application\AlarmScheduling\NativeAlarmGateway;
use App\Application\Preferences\AppPreferences;
use App\Models\Alarm;
use App\Models\AlarmExecution;
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
        ->assertSee('1 de 2 despertares a tiempo')
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
        ->assertSee('1 de 1 despertares a tiempo')
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
