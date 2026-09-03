<?php

use App\Application\AlarmAnalytics\AlarmHabitsAnalytics;
use App\Models\AlarmExecution;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('summarizes terminal results for seven Managua calendar days', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));

    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => CarbonImmutable::parse('2026-08-28 07:00:00', 'America/Managua')->utc(),
    ]);
    AlarmExecution::factory()->create([
        'status' => 'missed',
        'scheduled_for' => CarbonImmutable::parse('2026-08-30 07:00:00', 'America/Managua')->utc(),
    ]);
    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-03 20:00:00', 'America/Managua')->utc(),
    ]);

    $summary = app(AlarmHabitsAnalytics::class)->summarize();

    expect($summary)->toMatchArray([
        'completed' => 2,
        'missed' => 1,
        'total' => 3,
        'completion_rate' => 67,
    ])->and($summary['days'])->toBe([
        ['date' => '2026-08-28', 'completed' => 1, 'missed' => 0],
        ['date' => '2026-08-29', 'completed' => 0, 'missed' => 0],
        ['date' => '2026-08-30', 'completed' => 0, 'missed' => 1],
        ['date' => '2026-08-31', 'completed' => 0, 'missed' => 0],
        ['date' => '2026-09-01', 'completed' => 0, 'missed' => 0],
        ['date' => '2026-09-02', 'completed' => 0, 'missed' => 0],
        ['date' => '2026-09-03', 'completed' => 1, 'missed' => 0],
    ]);
});

it('excludes non-terminal and future executions', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));

    foreach (['scheduled', 'ringing', 'snoozed', 'cancelled'] as $status) {
        AlarmExecution::factory()->create([
            'status' => $status,
            'scheduled_for' => CarbonImmutable::parse('2026-09-03 07:00:00', 'America/Managua')->utc(),
        ]);
    }
    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-04 07:00:00', 'America/Managua')->utc(),
    ]);

    $summary = app(AlarmHabitsAnalytics::class)->summarize();

    expect($summary['total'])->toBe(0)
        ->and($summary['completion_rate'])->toBe(0);
});
