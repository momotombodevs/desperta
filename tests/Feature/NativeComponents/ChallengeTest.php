<?php

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
    $scheduler->shouldReceive('cancel')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);

    completeChallenge($challenge);

    $challenge
        ->assertSet('completed', true)
        ->assertSet('passed', true)
        ->assertSet('alarmStopped', true)
        ->assertSee('La alarma ya está apagada.')
        ->assertDontSee('Apagar alarma');

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

it('completes a repeating alarm without cancelling its future schedule', function () {
    $alarm = Alarm::factory()->create([
        'weekdays' => [1, 3, 5],
        'scheduling_status' => 'scheduled',
    ]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    $scheduler->shouldNotReceive('cancel');
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);

    completeChallenge($challenge);

    $challenge->assertSet('alarmStopped', true);

    $this->assertDatabaseHas('alarms', [
        'id' => $alarm->id,
        'enabled' => true,
        'scheduling_status' => 'scheduled',
    ]);
});

it('keeps a failed challenge ringing until the user retries', function () {
    $alarm = Alarm::factory()->create(['scheduling_status' => 'scheduled']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldNotReceive('completeRinging');
    $scheduler->shouldNotReceive('cancel');
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);
    $answers = $challenge->get('questions');

    foreach ($answers as $index => $question) {
        $wrongAnswer = collect($question['options'])->first(fn (string $option): bool => $option !== $question['answer']);
        $challenge->call('selectAnswer', $wrongAnswer)->call('continueChallenge');
    }

    $challenge
        ->assertSet('completed', true)
        ->assertSet('passed', false)
        ->assertSet('alarmStopped', false)
        ->assertSee('Todavía no. Intentá de nuevo.');

    $this->assertDatabaseHas('alarm_challenge_attempts', [
        'alarm_id' => $alarm->id,
        'attempt_number' => 1,
        'passed' => false,
    ]);
});

it('renders selected answer cards accessibly', function () {
    $challenge = Native::test(Challenge::class);
    $answer = $challenge->get('questions')[0]['options'][0];

    $challenge
        ->call('selectAnswer', $answer)
        ->assertSet('selectedAnswer', $answer)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'answer-0'
            && ($node['style']['border_width'] ?? null) === 1.0)
        ->assertAccessible();
});

/** @param TestableComponent<Challenge> $challenge */
function completeChallenge($challenge): void
{
    foreach ($challenge->get('questions') as $question) {
        $challenge->call('selectAnswer', $question['answer'])->call('continueChallenge');
    }
}
