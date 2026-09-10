<?php

use App\AlarmScheduling\ActiveAlarmOccurrence;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Models\Alarm;
use App\Models\AlarmExecution;
use App\NativeComponents\AlarmEditor;
use App\NativeComponents\Challenge;
use App\NativeComponents\Habits;
use App\NativeComponents\History;
use App\NativeComponents\Home;
use App\NativeComponents\Settings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Momotombo\NativePHPAlarms\Events\AppResumed;
use Native\Mobile\Testing\Native;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

it('opens the active challenge when Android resumes another screen', function (string $screen) {
    $alarm = Alarm::factory()->create();
    $active = null;
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturnUsing(function () use (&$active): ?ActiveAlarmOccurrence {
            return $active;
        });
    $component = Native::test($screen);
    $active = new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-04T07:00:00Z');

    $component->emitNative(AppResumed::class, [])->assertReplacedWith('/challenge')
        ->follow()->assertScreen(Challenge::class)->assertSet('executionId', 'execution-1');
})->with([Home::class, Settings::class, AlarmEditor::class, Habits::class, History::class]);

it('keeps the current challenge and its selected answer across repeated Android resumes', function () {
    $alarm = Alarm::factory()->create();
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-04T07:00:00Z'));
    $challenge = Native::test(Challenge::class)->tap('answer-1')->tap('continue-challenge')->tap('answer-2');
    $questions = $challenge->get('questions');

    $challenge->emitNative(AppResumed::class, [])->emitNative(AppResumed::class, [])
        ->assertNoNavigation()->assertSet('questions', $questions)
        ->assertSet('questionIndex', 1)->assertSet('selectedAnswerIndex', 2);

    $this->assertDatabaseCount('alarm_executions', 1);
    $this->assertDatabaseCount('alarm_challenge_attempts', 0);
});

it('replaces an older visible challenge when a different occurrence is ringing', function () {
    $alarm = Alarm::factory()->create();
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')->andReturn(
        new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-04T07:00:00Z'),
        new ActiveAlarmOccurrence($alarm->id, 'execution-2', '2026-09-05T07:00:00Z'),
    );
    $challenge = Native::test(Challenge::class)->tap('answer-1');

    $challenge->emitNative(AppResumed::class, [])->assertReplacedWith('/challenge')
        ->follow()->assertSet('executionId', 'execution-2')->assertSet('selectedAnswerIndex', null);
});

it('does not redirect a resumed screen without a ringing alarm', function () {
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')->andReturnNull();

    Native::test(Settings::class)->emitNative(AppResumed::class, [])->assertNoNavigation();
});

it('does not reopen a snoozed alarm while native playback is stopped', function () {
    AlarmExecution::factory()->create(['status' => 'snoozed']);
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')->andReturnNull();

    Native::test(Home::class)->emitNative(AppResumed::class, [])->assertNoNavigation();
});

it('ignores terminal native occurrences when Android resumes', function (string $status) {
    $alarm = Alarm::factory()->create();
    $execution = AlarmExecution::factory()->for($alarm)->create(['status' => $status]);
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, $execution->id, '2026-09-04T07:00:00Z'));

    Native::test(Settings::class)->emitNative(AppResumed::class, [])->assertNoNavigation();

    expect($execution->fresh()->status)->toBe($status);
})->with(['completed', 'cancelled', 'missed']);

it('keeps the highlighted home row as a manual recovery action', function () {
    $alarm = Alarm::factory()->create();
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')->andReturn(
        null,
        new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-04T07:00:00Z'),
    );

    Native::test(Home::class)->set('activeAlarmId', $alarm->id)
        ->assertSee('Sonando · Continuar reto')->assertAccessible()
        ->tap("edit-alarm-{$alarm->id}")->assertNavigatedTo('/challenge');
});
