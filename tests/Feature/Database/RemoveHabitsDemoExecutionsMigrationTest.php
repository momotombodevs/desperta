<?php

use App\Models\AlarmExecution;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('removes only historical demo executions', function () {
    $demoExecution = AlarmExecution::factory()->create([
        'alarm_id' => null,
        'alarm_label' => '[DEMO] Historial de hábitos',
    ]);
    $realExecution = AlarmExecution::factory()->create();

    $migration = require database_path('migrations/2026_09_04_180710_remove_habits_demo_executions.php');

    $migration->up();

    expect(AlarmExecution::query()->find($demoExecution->id))->toBeNull()
        ->and(AlarmExecution::query()->find($realExecution->id))->not->toBeNull();
});
