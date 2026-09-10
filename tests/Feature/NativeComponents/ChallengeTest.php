<?php

use App\AlarmScheduling\ActiveAlarmOccurrence;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Models\Alarm;
use App\NativeComponents\Challenge;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

it('stops a one-time alarm after one successful challenge', function () {
    $alarm = Alarm::factory()->create([
        'weekdays' => [],
        'scheduling_status' => 'scheduled',
    ]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('cancel')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);

    completeChallenge($challenge);

    $challenge
        ->assertSet('completed', true)
        ->assertSet('passed', true)
        ->assertSet('alarmStopped', true);

    $this->assertDatabaseHas('alarms', [
        'id' => $alarm->id,
        'enabled' => false,
        'scheduling_status' => 'not_scheduled',
    ]);
    $this->assertDatabaseHas('alarm_challenge_attempts', [
        'alarm_id' => $alarm->id,
        'attempt_number' => 1,
        'passed' => true,
    ]);
});

it('stops the active native alarm when a notification opens the challenge without route data', function () {
    $alarm = Alarm::factory()->create([
        'scheduling_status' => 'scheduled',
    ]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class);

    completeChallenge($challenge);

    $challenge
        ->assertSet('alarmId', $alarm->id)
        ->assertSet('alarmStopped', true);
});

it('starts the scheduled execution passed through the native challenge route', function () {
    $alarm = Alarm::factory()->create();
    $executionId = '018f0b8d-1d3e-7f14-8caa-111111111111';

    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, $executionId, '2026-09-03T07:00:00+00:00'));

    Native::visit("/challenge/{$alarm->id}/{$executionId}/2026-09-03T07:00:00+00:00")
        ->assertSet('alarmId', $alarm->id)
        ->assertSet('executionId', $executionId);

    $this->assertDatabaseHas('alarm_executions', [
        'id' => $executionId,
        'alarm_id' => $alarm->id,
        'status' => 'ringing',
    ]);
});

it('completes a repeating alarm without cancelling its future schedule', function () {
    $alarm = Alarm::factory()->create([
        'weekdays' => [1, 3, 5],
        'scheduling_status' => 'scheduled',
    ]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    $scheduler->shouldNotReceive('cancel');
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);

    completeChallenge($challenge);

    $challenge
        ->assertSet('alarmStopped', true);

    $this->assertDatabaseHas('alarms', [
        'id' => $alarm->id,
        'enabled' => true,
        'scheduling_status' => 'scheduled',
    ]);
});

it('keeps a failed challenge ringing until the user retries', function () {
    $alarm = Alarm::factory()->create(['scheduling_status' => 'scheduled']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldNotReceive('completeRinging');
    $scheduler->shouldNotReceive('cancel');
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);
    $answers = $challenge->get('questions');

    foreach ($answers as $index => $question) {
        $wrongAnswerIndex = array_find_key($question['options'], fn (string $option): bool => $option !== $question['answer']);
        $challenge->call('selectAnswer', $wrongAnswerIndex)->call('continueChallenge');
    }

    $challenge
        ->assertSet('completed', true)
        ->assertSet('passed', false)
        ->assertSet('alarmStopped', false)
        ->assertSee('Necesitás 3 de 3. Probá con otras preguntas.');

    $this->assertDatabaseHas('alarm_challenge_attempts', [
        'alarm_id' => $alarm->id,
        'attempt_number' => 1,
        'passed' => false,
    ]);
});

it('allows an easy challenge to stop after two correct answers', function () {
    $alarm = Alarm::factory()->create(['difficulty' => 'easy']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);
    answerQuestions($challenge, [true, true, false]);

    $challenge
        ->assertSet('questionCount', 3)
        ->assertSet('requiredCorrectAnswers', 2)
        ->assertSet('passed', true)
        ->assertSet('alarmStopped', true);

    $this->assertDatabaseHas('alarm_challenge_attempts', [
        'alarm_id' => $alarm->id,
        'question_count' => 3,
        'required_correct_answers' => 2,
        'correct_answers' => 2,
        'passed' => true,
    ]);
});

it('requires five correct answers for a hard challenge', function () {
    $alarm = Alarm::factory()->create(['difficulty' => 'hard']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);
    completeChallenge($challenge);

    $challenge
        ->assertSet('questionCount', 5)
        ->assertSet('requiredCorrectAnswers', 5)
        ->assertSet('passed', true);

    $this->assertDatabaseHas('alarm_challenge_attempts', [
        'alarm_id' => $alarm->id,
        'question_count' => 5,
        'required_correct_answers' => 5,
        'correct_answers' => 5,
        'passed' => true,
    ]);
});

it('renders selected answer cards accessibly', function () {
    $alarm = Alarm::factory()->create();
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $challenge = Native::test(Challenge::class);
    $answerIndex = 0;

    $challenge
        ->call('selectAnswer', $answerIndex)
        ->assertSet('selectedAnswerIndex', $answerIndex)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'answer-0'
            && ($node['style']['border_width'] ?? null) === 1.0)
        ->assertAccessible();
});

/** @param TestableComponent<Challenge> $challenge */
function completeChallenge($challenge): void
{
    foreach ($challenge->get('questions') as $question) {
        $answerIndex = array_find_key($question['options'], fn (string $option): bool => $option === $question['answer']);
        $challenge->call('selectAnswer', $answerIndex)->call('continueChallenge');
    }
}

function answerQuestions($challenge, array $answers): void
{
    foreach ($challenge->get('questions') as $index => $question) {
        $answerIndex = array_find_key($question['options'], fn (string $option): bool => $answers[$index]
            ? $option === $question['answer']
            : $option !== $question['answer']);
        $challenge->call('selectAnswer', $answerIndex)->call('continueChallenge');
    }
}
