<?php

use App\Application\AlarmAnalytics\AlarmHabitsAnalytics;
use App\Application\Preferences\AppPreferences;
use App\Models\AlarmExecution;
use Carbon\CarbonImmutable;
use Database\Seeders\HabitsDemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Native\Mobile\Testing\Native;

uses(LazilyRefreshDatabase::class);

it('seeds abundant mixed history that renders meaningful habit metrics', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-04 14:00:00', 'UTC'));
    app(AppPreferences::class)->setLanguage('es_NI');
    $realExecution = AlarmExecution::factory()->create([
        'scheduled_for' => now()->subYear(),
    ]);

    (new HabitsDemoSeeder)->run();

    $demoExecutions = AlarmExecution::query()
        ->whereNull('alarm_id')
        ->where('alarm_label', HabitsDemoSeeder::AlarmLabel)
        ->get();
    $summary = app(AlarmHabitsAnalytics::class)->summarize();

    expect($demoExecutions)->toHaveCount(180)
        ->and($demoExecutions->where('status', 'missed'))->toHaveCount(11)
        ->and($demoExecutions->filter(fn (AlarmExecution $execution): bool => $execution->status === 'completed'
            && $execution->finished_at->greaterThan($execution->scheduled_for->addMinutes(10))))->toHaveCount(25)
        ->and($demoExecutions->filter(fn (AlarmExecution $execution): bool => $execution->status === 'completed'
            && $execution->finished_at->lessThanOrEqualTo($execution->scheduled_for->addMinutes(10))))->toHaveCount(144)
        ->and($demoExecutions->where('snooze_count', '>', 0))->toHaveCount(60)
        ->and($summary)->toMatchArray([
            'current_streak' => 2,
            'on_time_count' => 4,
            'resolved_count' => 6,
            'on_time_rate' => 67,
            'without_snooze_count' => 3,
            'without_snooze_rate' => 60,
        ]);
    $this->assertModelExists($realExecution);
    Native::visit('/settings/habits')
        ->assertSee('4 de 6 despertares a tiempo')
        ->assertDontSee('Todavía no hay hábitos que mostrar')
        ->assertElement('bar_chart')
        ->assertElement('donut_chart');
});

it('replaces its own demo history without duplicating records', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-04 14:00:00', 'UTC'));
    $seeder = new HabitsDemoSeeder;
    $seeder->run();
    $originalIds = AlarmExecution::query()
        ->whereNull('alarm_id')
        ->where('alarm_label', HabitsDemoSeeder::AlarmLabel)
        ->orderBy('id')
        ->pluck('id')
        ->all();

    $seeder->run();

    $reseededIds = AlarmExecution::query()
        ->whereNull('alarm_id')
        ->where('alarm_label', HabitsDemoSeeder::AlarmLabel)
        ->orderBy('id')
        ->pluck('id')
        ->all();
    expect($reseededIds)->toHaveCount(180)
        ->toBe($originalIds);
});

it('runs through its migration locally and rolls back only demo history', function () {
    $realExecution = AlarmExecution::factory()->create();
    config()->set('app.habits_demo_data', true);
    $migration = require database_path('migrations/2026_09_04_021217_seed_habits_demo_data.php');

    $migration->up();

    expect(AlarmExecution::query()->where('alarm_label', HabitsDemoSeeder::AlarmLabel)->count())->toBe(180);
    $this->assertModelExists($realExecution);

    $migration->down();

    expect(AlarmExecution::query()->where('alarm_label', HabitsDemoSeeder::AlarmLabel)->count())->toBe(0);
    $this->assertModelExists($realExecution);
});

it('does not seed demo history when the local demo flag is disabled', function () {
    config()->set('app.habits_demo_data', false);
    $migration = require database_path('migrations/2026_09_04_021217_seed_habits_demo_data.php');

    $migration->up();

    expect(AlarmExecution::query()->where('alarm_label', HabitsDemoSeeder::AlarmLabel)->doesntExist())->toBeTrue();
});
