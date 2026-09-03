<?php

use App\Application\Preferences\AppPreferences;
use App\Models\AlarmExecution;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Native\Mobile\Testing\Native;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    app(AppPreferences::class)->setLanguage('es_NI');
});

it('renders the accessible localized empty state when no executions exist', function () {
    Native::visit('/settings/history')
        ->assertSee('Todavía no hay ejecuciones')
        ->assertSee('Completá una alarma para ver aquí su historial de ejecuciones.')
        ->assertAccessible();
});

it('keeps rendering execution rows when executions exist', function () {
    AlarmExecution::factory()->create([
        'alarm_label' => 'Prepararme para trabajar',
    ]);

    Native::visit('/settings/history')
        ->assertSee('Prepararme para trabajar')
        ->assertDontSee('Todavía no hay ejecuciones')
        ->assertAccessible();
});

it('renders the empty state in English when English is selected', function () {
    app(AppPreferences::class)->setLanguage('en');

    Native::visit('/settings/history')
        ->assertSee('No executions yet')
        ->assertSee('Complete an alarm to see its execution history here.')
        ->assertAccessible();
});
