<?php

use App\AlarmScheduling\ActiveAlarmOccurrence;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Application\Challenges\AdaptiveChallengeDifficulty;
use App\Application\Challenges\ChallengeDifficulty;
use App\Models\Alarm;
use App\Models\AlarmChallengeAttempt;
use App\Models\AlarmExecution;
use App\NativeComponents\Challenge;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Native\Mobile\Testing\Native;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

it('raises difficulty after three fast perfect challenge executions without snoozing', function () {
    $alarm = Alarm::factory()->create(['difficulty' => 'normal']);

    foreach (range(1, 3) as $offset) {
        $startedAt = CarbonImmutable::parse("2026-10-0{$offset} 07:00:00", 'America/Managua');
        $execution = AlarmExecution::factory()->for($alarm)->create([
            'status' => 'completed',
            'started_at' => $startedAt,
            'finished_at' => $startedAt->addSeconds(90),
            'snooze_count' => 0,
        ]);
        AlarmChallengeAttempt::factory()->for($alarm)->create([
            'alarm_execution_id' => $execution->id,
            'correct_answers' => 3,
            'question_count' => 3,
            'passed' => true,
        ]);
    }

    expect(app(AdaptiveChallengeDifficulty::class)->forAlarm($alarm))->toBe(ChallengeDifficulty::Hard);
});

it('lowers difficulty after two consecutive failed attempts', function () {
    $alarm = Alarm::factory()->create(['difficulty' => 'hard']);
    $execution = AlarmExecution::factory()->for($alarm)->create(['status' => 'completed']);

    foreach ([1, 2] as $attemptNumber) {
        AlarmChallengeAttempt::factory()->for($alarm)->create([
            'alarm_execution_id' => $execution->id,
            'attempt_number' => $attemptNumber,
            'correct_answers' => 2,
            'question_count' => 5,
            'passed' => false,
        ]);
    }

    expect(app(AdaptiveChallengeDifficulty::class)->forAlarm($alarm))->toBe(ChallengeDifficulty::Normal);
});

it('lowers difficulty for recent failures in missed executions despite a later pass', function () {
    $alarm = Alarm::factory()->create(['difficulty' => 'hard']);

    foreach ([
        ['status' => 'missed', 'finished_at' => CarbonImmutable::parse('2026-10-01 07:10:00', 'America/Managua'), 'passed' => false],
        ['status' => 'cancelled', 'finished_at' => CarbonImmutable::parse('2026-10-02 07:10:00', 'America/Managua'), 'passed' => false],
        ['status' => 'completed', 'finished_at' => CarbonImmutable::parse('2026-10-03 07:02:00', 'America/Managua'), 'passed' => true],
    ] as $index => $result) {
        $execution = AlarmExecution::factory()->for($alarm)->create([
            'status' => $result['status'],
            'started_at' => $result['finished_at']->subMinutes(2),
            'finished_at' => $result['finished_at'],
        ]);
        AlarmChallengeAttempt::factory()->for($alarm)->create([
            'alarm_execution_id' => $execution->id,
            'attempt_number' => 1,
            'correct_answers' => $result['passed'] ? 5 : 2,
            'question_count' => 5,
            'passed' => $result['passed'],
            'created_at' => $result['finished_at']->addSeconds($index),
        ]);
    }

    expect(app(AdaptiveChallengeDifficulty::class)->forAlarm($alarm))->toBe(ChallengeDifficulty::Normal);
});

it('keeps the configured difficulty when history is insufficient or performance is not consistently fast', function () {
    $alarm = Alarm::factory()->create(['difficulty' => 'normal']);

    expect(app(AdaptiveChallengeDifficulty::class)->forAlarm($alarm))->toBe(ChallengeDifficulty::Normal);

    foreach (range(1, 3) as $offset) {
        $startedAt = CarbonImmutable::parse("2026-10-0{$offset} 07:00:00", 'America/Managua');
        $execution = AlarmExecution::factory()->for($alarm)->create([
            'status' => 'completed',
            'started_at' => $startedAt,
            'finished_at' => $startedAt->addMinutes(8),
            'snooze_count' => $offset === 3 ? 1 : 0,
        ]);
        AlarmChallengeAttempt::factory()->for($alarm)->create([
            'alarm_execution_id' => $execution->id,
            'correct_answers' => 3,
            'question_count' => 3,
            'passed' => true,
        ]);
    }

    expect(app(AdaptiveChallengeDifficulty::class)->forAlarm($alarm))->toBe(ChallengeDifficulty::Normal);
});

it('starts the next challenge at the policy-selected difficulty', function () {
    $this->travelTo('2026-10-09 07:00:00');
    $alarm = Alarm::factory()->create(['difficulty' => 'easy']);

    foreach (range(1, 3) as $offset) {
        $startedAt = CarbonImmutable::parse("2026-10-0{$offset} 07:00:00", 'America/Managua');
        $execution = AlarmExecution::factory()->for($alarm)->create([
            'status' => 'completed',
            'started_at' => $startedAt,
            'finished_at' => $startedAt->addSeconds(90),
            'snooze_count' => 0,
        ]);
        AlarmChallengeAttempt::factory()->for($alarm)->create([
            'alarm_execution_id' => $execution->id,
            'correct_answers' => 3,
            'question_count' => 3,
            'passed' => true,
        ]);
    }

    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'new-execution', '2026-10-09T07:00:00-06:00'));

    Native::test(Challenge::class)
        ->assertSet('difficulty', 'normal')
        ->assertSet('questionCount', 3)
        ->assertSet('requiredCorrectAnswers', 3);
});
