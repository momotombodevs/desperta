<?php

use App\Application\Preferences\AppPreferences;
use App\Models\AlarmExecution;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Native\Mobile\Testing\Native;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    app(AppPreferences::class)->setLanguage('es_NI');
});

it('renders real habit charts for terminal executions in the selected period', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));

    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-02 07:00:00', 'America/Managua')->utc(),
    ]);
    AlarmExecution::factory()->create([
        'status' => 'missed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-03 07:00:00', 'America/Managua')->utc(),
    ]);

    Native::visit('/settings/habits')
        ->assertSee('50%')
        ->assertSee('1 de 2 alarmas completadas')
        ->assertSee('Resultados diarios')
        ->assertSee('Distribución de resultados')
        ->assertElement('bar_chart')
        ->assertElement('donut_chart');
});

it('renders the localized empty state when the period has no terminal executions', function () {
    Native::visit('/settings/habits')
        ->assertSee('Todavía no hay hábitos que mostrar')
        ->assertDontSee('Resultados diarios')
        ->assertMissingElement('bar_chart')
        ->assertMissingElement('donut_chart');
});

it('uses English habit labels when English is selected', function () {
    app(AppPreferences::class)->setLanguage('en');

    Native::visit('/settings/habits')
        ->assertSee('Habits')
        ->assertSee('No habits to show yet');
});
