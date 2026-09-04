<?php

use App\AlarmScheduling\ActiveAlarmOccurrence;
use App\AlarmScheduling\NativeAlarmOccurrenceEvent;
use App\Application\AlarmScheduling\AlarmExecutionLifecycle;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Application\Preferences\AppPreferences;
use App\Models\Alarm;
use App\Models\AlarmExecution;
use App\NativeComponents\Challenge;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Momotombo\NativePHPAlarms\Events\AppResumed;
use Native\Mobile\Testing\Native;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

it('restores the same questions selection and score after reopening through the notification', function () {
    $this->travelTo('2026-09-04 07:00:00');
    $alarm = Alarm::factory()->create(['difficulty' => 'easy']);
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-04T07:00:00Z'));
    $challenge = Native::test(Challenge::class);
    $questions = $challenge->get('questions');
    $correct = array_search($questions[0]['answer'], $questions[0]['options'], true);
    $challenge->tap("answer-{$correct}")->tap('continue-challenge')->tap('answer-1');
    $this->travel(10)->minutes();
    $alarm->update(['difficulty' => 'hard']);
    app(AppPreferences::class)->setChallengeTheme('math');

    Native::test(Challenge::class)
        ->assertSet('questions', $questions)
        ->assertSet('usedQuestionIds', array_column($questions, 'id'))
        ->assertSet('questionIndex', 1)
        ->assertSet('selectedAnswerIndex', 1)
        ->assertSet('correctAnswers', 1)
        ->assertSet('difficulty', 'easy')
        ->assertSet('requiredCorrectAnswers', 2)
        ->assertSet('challengeTheme', 'nicaragua')
        ->assertSet('attemptNumber', 1);

    expect(AlarmExecution::query()->findOrFail('execution-1')->started_at->toDateTimeString())->toBe('2026-09-04 07:00:00');
    $this->assertDatabaseCount('alarm_challenge_attempts', 0);
});

it('preserves a failed attempt and saves the retry without duplicating history', function () {
    $alarm = Alarm::factory()->create();
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-04T07:00:00Z'));
    $challenge = Native::test(Challenge::class);
    $firstQuestions = $challenge->get('questions');
    foreach ($firstQuestions as $question) {
        $wrong = array_find_key($question['options'], fn (string $answer): bool => $answer !== $question['answer']);
        $challenge->tap("answer-{$wrong}")->tap('continue-challenge');
    }
    app(AppPreferences::class)->setChallengeTheme('math');

    $resumed = Native::test(Challenge::class)->assertSet('completed', true)->assertSet('passed', false);
    $resumed->call('continueChallenge')->tap('retry-challenge');
    $retryQuestions = $resumed->get('questions');
    Native::test(Challenge::class)
        ->assertSet('questions', $retryQuestions)
        ->assertSet('completed', false)
        ->assertSet('questionIndex', 0)
        ->assertSet('attemptNumber', 2)
        ->assertSet('challengeTheme', 'nicaragua');

    expect(array_intersect(array_column($firstQuestions, 'id'), array_column($retryQuestions, 'id')))->toBeEmpty();
    $themeIds = array_column(trans('challenges.nicaragua.questions'), 'id');
    expect(array_diff(array_column($retryQuestions, 'id'), $themeIds))->toBeEmpty();
    $this->assertDatabaseCount('alarm_challenge_attempts', 1);
});

it('retains progress while snoozed and restores it when the same occurrence rings again', function () {
    $alarm = Alarm::factory()->create(['snooze_enabled' => true, 'snooze_minutes' => 10]);
    $occurrence = new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-04T07:00:00Z');
    $scheduler = mock(NativeAlarmScheduler::class);
    $active = $occurrence;
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturnUsing(function () use (&$active): ?ActiveAlarmOccurrence {
        return $active;
    });
    $scheduler->shouldReceive('snooze')->once()->with($alarm->id, 10);
    $challenge = Native::test(Challenge::class)->tap('answer-2');
    $questions = $challenge->get('questions');
    $challenge->tap('snooze-alarm')->assertReplacedWith('/');
    $execution = AlarmExecution::query()->findOrFail('execution-1');
    expect($execution->status)->toBe('snoozed');
    expect($execution->challenge_progress['selectedAnswerIndex'])->toBe(2);

    $active = null;
    Native::test(Challenge::class)->assertReplacedWith('/');
    $active = $occurrence;
    Native::test(Challenge::class)->assertSet('questions', $questions)->assertSet('selectedAnswerIndex', 2);
});

it('starts fresh for a new occurrence and removes the previous pending progress', function () {
    $alarm = Alarm::factory()->create();
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(
        new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-04T07:00:00Z'),
        new ActiveAlarmOccurrence($alarm->id, 'execution-2', '2026-09-05T07:00:00Z'),
    );
    Native::test(Challenge::class)->tap('answer-1');

    Native::test(Challenge::class)->assertSet('executionId', 'execution-2')
        ->assertSet('selectedAnswerIndex', null)->assertSet('questionIndex', 0);

    $this->assertDatabaseHas('alarm_executions', ['id' => 'execution-1', 'status' => 'missed', 'challenge_progress' => null]);
});

it('rejects terminal executions even when native state still reports them as active', function (string $status) {
    $alarm = Alarm::factory()->create();
    $execution = AlarmExecution::factory()->for($alarm)->create(['status' => $status]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, $execution->id, '2026-09-04T07:00:00Z'));
    $scheduler->shouldNotReceive('completeRinging');
    $scheduler->shouldNotReceive('cancel');

    Native::test(Challenge::class)->assertReplacedWith('/');

    expect($execution->fresh()->status)->toBe($status);
    $this->assertDatabaseCount('alarm_challenge_attempts', 0);
})->with(['completed', 'cancelled', 'missed']);

it('rejects a notification for a different occurrence without starting it', function () {
    $alarm = Alarm::factory()->create();
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'current-execution', '2026-09-05T07:00:00Z'));

    Native::test(Challenge::class, data: ['alarmId' => $alarm->id, 'executionId' => 'old-execution'])
        ->assertReplacedWith('/');

    $this->assertDatabaseCount('alarm_executions', 0);
});

it('clears completed progress and records only one attempt after repeated callbacks', function () {
    $alarm = Alarm::factory()->create();
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-04T07:00:00Z'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    $challenge = Native::test(Challenge::class);
    foreach ($challenge->get('questions') as $question) {
        $correct = array_search($question['answer'], $question['options'], true);
        $challenge->tap("answer-{$correct}")->tap('continue-challenge');
    }
    $challenge->call('turnOffAlarm')->assertSet('alarmStopped', true);

    Native::test(Challenge::class)->assertReplacedWith('/');

    $this->assertDatabaseHas('alarm_executions', ['id' => 'execution-1', 'status' => 'completed', 'challenge_progress' => null]);
    $this->assertDatabaseCount('alarm_challenge_attempts', 1);
});

it('removes pending progress when an occurrence is cancelled or reconciled as terminal', function (string $status) {
    $alarm = Alarm::factory()->create();
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-04T07:00:00Z'));
    $challenge = Native::test(Challenge::class)->tap('answer-1');
    $lifecycle = app(AlarmExecutionLifecycle::class);
    if ($status === 'cancelled') {
        $lifecycle->cancelOpen($alarm);
    } else {
        $lifecycle->reconcile(new NativeAlarmOccurrenceEvent($alarm->id, 'execution-1', '2026-09-04T07:00:00Z', $status, '2026-09-04T07:10:00Z'));
    }

    $challenge->emitNative(AppResumed::class, [])->assertReplacedWith('/');

    $this->assertDatabaseHas('alarm_executions', ['id' => 'execution-1', 'status' => $status, 'challenge_progress' => null]);
})->with(['cancelled', 'completed', 'missed']);

it('ignores completion events belonging to another alarm', function () {
    $alarm = Alarm::factory()->create();
    $otherAlarm = Alarm::factory()->create();
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-04T07:00:00Z'));
    Native::test(Challenge::class)->tap('answer-1');
    $progress = AlarmExecution::query()->findOrFail('execution-1')->challenge_progress;

    $accepted = app(AlarmExecutionLifecycle::class)->reconcile(new NativeAlarmOccurrenceEvent(
        $otherAlarm->id, 'execution-1', '2026-09-04T07:00:00Z', 'completed', '2026-09-04T07:10:00Z',
    ));

    expect($accepted)->toBeFalse();
    expect(AlarmExecution::query()->findOrFail('execution-1'))
        ->status->toBe('ringing')->challenge_progress->toBe($progress);
});

it('does not stop a newer native occurrence from a stale challenge screen', function () {
    $alarm = Alarm::factory()->create();
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(
        new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-04T07:00:00Z'),
        new ActiveAlarmOccurrence($alarm->id, 'execution-2', '2026-09-05T07:00:00Z'),
    );
    $scheduler->shouldNotReceive('completeRinging');
    $scheduler->shouldNotReceive('cancel');
    $challenge = Native::test(Challenge::class);

    foreach ($challenge->get('questions') as $question) {
        $correct = array_search($question['answer'], $question['options'], true);
        $challenge->tap("answer-{$correct}")->tap('continue-challenge');
    }

    $challenge->assertReplacedWith('/');
    $this->assertDatabaseMissing('alarm_executions', ['id' => 'execution-1', 'status' => 'completed']);
});

it('keeps the original start time when the snoozed occurrence triggers again', function () {
    $alarm = Alarm::factory()->create();
    $execution = AlarmExecution::factory()->for($alarm)->create([
        'status' => 'snoozed', 'started_at' => '2026-09-04 07:00:00',
    ]);

    app(AlarmExecutionLifecycle::class)->reconcile(new NativeAlarmOccurrenceEvent(
        $alarm->id, $execution->id, '2026-09-04T07:00:00Z', 'triggered', '2026-09-04T07:10:00Z',
    ));

    expect($execution->fresh()->started_at->toDateTimeString())->toBe('2026-09-04 07:00:00');
});

it('automatically resumes saved progress when opening home', function () {
    $alarm = Alarm::factory()->create();
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-04T07:00:00Z'));
    $challenge = Native::test(Challenge::class)->tap('answer-2')->tap('continue-challenge')->tap('answer-0');
    $questions = $challenge->get('questions');

    Native::visit('/')->assertSee('Sonando · Continuar reto')
        ->assertReplacedWith('/challenge')->follow()
        ->assertSet('questions', $questions)
        ->assertSet('questionIndex', 1)
        ->assertSet('selectedAnswerIndex', 0);
});

it('adds nullable progress to an existing execution without changing its history', function () {
    $execution = AlarmExecution::factory()->create(['status' => 'completed']);
    $history = $execution->fresh()->getAttributes();
    unset($history['challenge_progress']);
    $migration = require database_path('migrations/2026_09_04_214133_add_challenge_progress_to_alarm_executions_table.php');
    $migration->down();

    $migration->up();

    $this->assertDatabaseHas('alarm_executions', [...$history, 'challenge_progress' => null]);
});
