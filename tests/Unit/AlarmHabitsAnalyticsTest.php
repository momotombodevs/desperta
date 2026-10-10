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

it('summarizes weekly punctual mornings once per local date and reports observed failures and snoozes', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));
    $monday = CarbonImmutable::parse('2026-08-31 07:00:00', 'America/Managua')->utc();
    AlarmExecution::factory()->count(2)->create([
        'status' => 'completed',
        'scheduled_for' => $monday,
        'finished_at' => $monday->addMinutes(5),
        'snooze_count' => 0,
    ]);
    AlarmExecution::factory()->create([
        'status' => 'missed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-01 07:00:00', 'America/Managua')->utc(),
        'finished_at' => null,
        'snooze_count' => 1,
    ]);
    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => CarbonImmutable::parse('2026-09-02 07:00:00', 'America/Managua')->utc(),
        'finished_at' => CarbonImmutable::parse('2026-09-02 07:15:00', 'America/Managua')->utc(),
        'snooze_count' => 2,
    ]);

    $summary = app(AlarmHabitsAnalytics::class)->summarize()['weekly_summary'];

    expect($summary)->toMatchArray([
        'on_time_mornings' => 1,
        'resolved_count' => 4,
        'on_time_count' => 2,
        'late_count' => 1,
        'missed_count' => 1,
        'snooze_count' => 3,
        'recommendation' => 'habits_recommendation_adjust',
    ]);
});

it('does not count an on-time snoozed execution as a weekly goal success', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));
    $scheduledFor = CarbonImmutable::parse('2026-09-02 07:00:00', 'America/Managua')->utc();
    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => $scheduledFor,
        'finished_at' => $scheduledFor->addMinutes(5),
        'snooze_count' => 1,
    ]);

    $summary = app(AlarmHabitsAnalytics::class)->summarize()['weekly_summary'];

    expect($summary['on_time_count'])->toBe(0)
        ->and($summary['on_time_mornings'])->toBe(0)
        ->and($summary['resolved_count'])->toBe(1);
});

it('returns a cautious recommendation when the current week has too little history', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));

    expect(app(AlarmHabitsAnalytics::class)->summarize()['weekly_summary'])->toMatchArray([
        'on_time_mornings' => 0,
        'resolved_count' => 0,
        'hardest_weekday' => null,
        'recommendation' => 'habits_recommendation_more_data',
    ]);
});

it('counts an on-time execution once toward the daily streak when another alarm that day failed', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));
    $scheduledFor = CarbonImmutable::parse('2026-09-03 07:00:00', 'America/Managua')->utc();
    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => $scheduledFor,
        'finished_at' => $scheduledFor->addMinutes(5),
    ]);
    AlarmExecution::factory()->create([
        'status' => 'missed',
        'scheduled_for' => $scheduledFor->addMinutes(30),
        'finished_at' => null,
    ]);

    expect(app(AlarmHabitsAnalytics::class)->summarize()['current_streak'])->toBe(1);
});
