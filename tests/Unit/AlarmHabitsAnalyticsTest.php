<?php

use App\Application\AlarmAnalytics\AlarmHabitsAnalytics;
use App\Models\AlarmExecution;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('summarizes punctual wake-ups, snoozes, and daily outcomes in Managua', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));

    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => CarbonImmutable::parse('2026-08-30 07:00:00', 'America/Managua')->utc(),
        'finished_at' => CarbonImmutable::parse('2026-08-30 07:10:00', 'America/Managua')->utc(),
        'snooze_count' => 0,
    ]);
    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => CarbonImmutable::parse('2026-08-31 07:00:00', 'America/Managua')->utc(),
        'finished_at' => CarbonImmutable::parse('2026-08-31 07:11:00', 'America/Managua')->utc(),
        'snooze_count' => 1,
    ]);
    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-03 07:00:00', 'America/Managua')->utc(),
        'finished_at' => CarbonImmutable::parse('2026-09-03 07:03:00', 'America/Managua')->utc(),
        'snooze_count' => 0,
    ]);

    $summary = app(AlarmHabitsAnalytics::class)->summarize();

    expect($summary)->toMatchArray([
        'current_streak' => 1,
        'best_streak' => 1,
        'on_time_count' => 2,
        'resolved_count' => 3,
        'on_time_rate' => 67,
        'without_snooze_count' => 2,
        'without_snooze_rate' => 67,
    ])->and($summary['days'][2])->toMatchArray([
        'date' => '2026-08-30',
        'status' => 'on_time',
    ])->and($summary['days'][3])->toMatchArray([
        'date' => '2026-08-31',
        'status' => 'late',
    ]);
});

it('keeps a current alarm pending until its ten minute window closes', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 13:05:00', 'UTC'));
    AlarmExecution::factory()->create([
        'status' => 'ringing',
        'scheduled_for' => CarbonImmutable::parse('2026-09-03 07:00:00', 'America/Managua')->utc(),
    ]);

    $summary = app(AlarmHabitsAnalytics::class)->summarize();

    expect($summary['resolved_count'])->toBe(0)
        ->and($summary['days'][6]['status'])->toBe('pending');
});

it('counts an expired unresolved alarm as missed', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 13:11:00', 'UTC'));
    AlarmExecution::factory()->create([
        'status' => 'snoozed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-03 07:00:00', 'America/Managua')->utc(),
    ]);

    $summary = app(AlarmHabitsAnalytics::class)->summarize();

    expect($summary['resolved_count'])->toBe(1)
        ->and($summary['on_time_rate'])->toBe(0)
        ->and($summary['days'][6]['status'])->toBe('missed');
});
