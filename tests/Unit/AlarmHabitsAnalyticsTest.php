<?php

use App\Application\AlarmAnalytics\AlarmHabitsAnalytics;
use App\Application\Preferences\BedtimeReminderSettings;
use App\Models\AlarmExecution;
use App\Models\AlarmExecutionRoutineStep;
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
        'current_streak' => 2,
        'best_streak' => 2,
        'protected_days' => ['2026-08-31'],
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

function wakeUpOn(string $date, bool $onTime): void
{
    $scheduledFor = CarbonImmutable::parse("{$date} 07:00:00", 'America/Managua')->utc();

    AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => $scheduledFor,
        'started_at' => $scheduledFor,
        'finished_at' => $scheduledFor->addMinutes($onTime ? 4 : 30),
    ]);
}

it('forgives one failed day and recharges the protector seven days later', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-12 18:00:00', 'UTC'));
    wakeUpOn('2026-08-31', true);
    wakeUpOn('2026-09-01', false);
    wakeUpOn('2026-09-02', true);
    wakeUpOn('2026-09-03', false);
    wakeUpOn('2026-09-04', true);
    wakeUpOn('2026-09-07', true);
    wakeUpOn('2026-09-08', false);
    wakeUpOn('2026-09-09', true);

    expect(app(AlarmHabitsAnalytics::class)->summarize())->toMatchArray([
        'current_streak' => 3,
        'best_streak' => 3,
        'protected_days' => ['2026-09-01', '2026-09-08'],
        'protector_available' => false,
    ]);
});

it('does not spend the protector before a streak has started', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-04 18:00:00', 'UTC'));
    wakeUpOn('2026-08-31', false);
    wakeUpOn('2026-09-01', true);

    expect(app(AlarmHabitsAnalytics::class)->summarize())->toMatchArray([
        'current_streak' => 1,
        'protected_days' => [],
        'protector_available' => true,
    ]);
});

it('lays out a month as whole Monday-first weeks', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 18:00:00', 'UTC'));
    wakeUpOn('2026-09-01', true);
    wakeUpOn('2026-09-02', false);

    $calendar = app(AlarmHabitsAnalytics::class)->calendar(CarbonImmutable::parse('2026-09-15'));
    $days = collect($calendar)->keyBy('date');

    expect($calendar)->toHaveCount(35)
        ->and($calendar[0]['date'])->toBe('2026-08-31')
        ->and($calendar[34]['date'])->toBe('2026-10-04')
        ->and($days['2026-08-31']['in_month'])->toBeFalse()
        ->and($days['2026-09-01'])->toMatchArray(['day' => 1, 'status' => 'on_time', 'protected' => false, 'in_month' => true])
        ->and($days['2026-09-02'])->toMatchArray(['status' => 'late', 'protected' => true])
        ->and($days['2026-09-04']['future'])->toBeTrue();
});
it('reports how long it takes to turn alarms off over two weeks', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 18:00:00', 'UTC'));

    expect(app(AlarmHabitsAnalytics::class)->wakeConsistency())->toBeNull();

    foreach (['2026-09-07 06:30' => 0, '2026-09-08 21:00' => 10, '2026-09-09 05:15' => 20] as $time => $offset) {
        $scheduledFor = CarbonImmutable::parse($time, 'America/Managua')->utc();
        AlarmExecution::factory()->create([
            'status' => 'completed',
            'scheduled_for' => $scheduledFor,
            'finished_at' => $scheduledFor->addMinutes($offset),
        ]);
    }

    expect(app(AlarmHabitsAnalytics::class)->wakeConsistency())->toBe([
        'samples' => 3,
        'average_delay_minutes' => 10,
        'variation_minutes' => 8,
    ]);
});

it('counts finished morning routines from the last seven days', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 18:00:00', 'UTC'));
    $recent = AlarmExecution::factory()->count(2)->create(['scheduled_for' => CarbonImmutable::parse('2026-09-09 13:00:00', 'UTC')]);
    $old = AlarmExecution::factory()->create(['scheduled_for' => CarbonImmutable::parse('2026-08-20 13:00:00', 'UTC')]);
    AlarmExecutionRoutineStep::query()->create(['alarm_execution_id' => $recent[0]->id, 'label' => 'Agua', 'position' => 0, 'completed_at' => now()]);
    AlarmExecutionRoutineStep::query()->create(['alarm_execution_id' => $recent[1]->id, 'label' => 'Agua', 'position' => 0]);
    AlarmExecutionRoutineStep::query()->create(['alarm_execution_id' => $old->id, 'label' => 'Agua', 'position' => 0, 'completed_at' => now()]);

    expect(app(AlarmHabitsAnalytics::class)->routineCompletion())->toBe(['started' => 2, 'completed' => 1]);
});

it('does not forgive back-to-back failures across a week boundary', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-08 18:00:00', 'UTC'));
    wakeUpOn('2026-09-04', true);
    wakeUpOn('2026-09-05', true);
    wakeUpOn('2026-09-06', false);
    wakeUpOn('2026-09-07', false);

    expect(app(AlarmHabitsAnalytics::class)->summarize())->toMatchArray([
        'current_streak' => 0,
        'best_streak' => 2,
        'protected_days' => ['2026-09-06'],
    ]);
});

it('measures the longest run of days without snoozing', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-12 18:00:00', 'UTC'));
    foreach (['2026-09-01' => 0, '2026-09-02' => 0, '2026-09-03' => 1, '2026-09-05' => 0, '2026-09-06' => 0, '2026-09-08' => 0] as $date => $snoozes) {
        $scheduledFor = CarbonImmutable::parse("{$date} 07:00:00", 'America/Managua')->utc();
        AlarmExecution::factory()->create([
            'status' => 'completed', 'scheduled_for' => $scheduledFor, 'finished_at' => $scheduledFor->addMinutes(3), 'snooze_count' => $snoozes,
        ]);
    }

    expect(app(AlarmHabitsAnalytics::class)->bestNoSnoozeRun())->toBe(3);
});

it('estimates sleep only on nights the bedtime reminder applies', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 18:00:00', 'UTC'));
    app(BedtimeReminderSettings::class)->save(true, '22:30', [1, 2, 3, 4, 5]);
    $analytics = app(AlarmHabitsAnalytics::class);

    expect($analytics->plannedSleep(CarbonImmutable::parse('2026-09-11 06:00', 'America/Managua')))->toBe(450)
        ->and($analytics->plannedSleep(CarbonImmutable::parse('2026-09-13 06:00', 'America/Managua')))->toBeNull()
        ->and($analytics->plannedSleep(CarbonImmutable::parse('2026-09-11 21:00', 'America/Managua')))->toBeNull()
        ->and($analytics->actualSleep())->toBeNull();
});
