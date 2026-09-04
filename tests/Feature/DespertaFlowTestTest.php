<?php

use App\AlarmScheduling\ActiveAlarmOccurrence;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Models\Alarm;
use App\Models\AlarmExecution;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Momotombo\NativePHPAlarms\Events\NotificationAuthorizationChanged;
use Native\Mobile\Testing\Native;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

it('opens the challenge directly for a ringing alarm', function () {
    Native::visit('/')
        ->assertDontSee('Tus alarmas')
        ->assertDontSee('Aún no tenés alarmas creadas.')
        ->assertAccessible()
        ->tap('create-alarm')
        ->assertNavigatedTo('/alarms/new')
        ->follow()
        ->assertSee('Nueva alarma');

    $alarm = Alarm::factory()->create(['time' => '07:15', 'label' => 'Universidad']);

    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00Z'));

    Native::visit('/challenge', ['alarmId' => $alarm->id])
        ->assertSet('alarmId', $alarm->id)
        ->assertSee('Pregunta 1 de 3');
});

it('persists and schedules an alarm created in the editor', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('canScheduleExactly')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPresentWhileLocked')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPostNotifications')->once()->andReturnTrue();
    $scheduler->shouldReceive('schedule')->once();
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::visit('/alarms/new')
        ->assertPicker('Hora', 'time')
        ->pickTime('time', '07:15')
        ->assertPickerValue('Hora', '07:15')
        ->set('label', 'Universidad')
        ->assertSet('label', 'Universidad')
        ->set('saturday', true)
        ->tap('save-alarm');

    $alarm = Alarm::query()->sole();

    expect($alarm->time)->toBe('07:15');
    expect($alarm->label)->toBe('Universidad');
    expect($alarm->weekdays)->toContain(6);
});

it('opens exact alarm settings instead of saving when Android rejects the permission', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('canScheduleExactly')->once()->andReturnFalse();
    $scheduler->shouldReceive('requestExactAlarmPermission')->once();
    $scheduler->shouldNotReceive('schedule');
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::visit('/alarms/new')
        ->tap('save-alarm')
        ->assertSet('resumeAfterExactAlarmPermission', true);
});

it('opens full-screen alarm settings when Android cannot show over the lock screen', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('canScheduleExactly')->once()->andReturnTrue();
    $scheduler->shouldReceive('canPresentWhileLocked')->once()->andReturnFalse();
    $scheduler->shouldReceive('requestFullScreenAlarmPermission')->once();
    $scheduler->shouldNotReceive('schedule');
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::visit('/alarms/new')
        ->tap('save-alarm')
        ->assertSet('resumeAfterFullScreenAlarmPermission', true)
        ->assertDontSee('Permití alarmas en pantalla completa');
});

it('waits for notification authorization before scheduling a locked-screen alarm', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('canScheduleExactly')->twice()->andReturnTrue();
    $scheduler->shouldReceive('canPresentWhileLocked')->twice()->andReturnTrue();
    $scheduler->shouldReceive('canPostNotifications')->twice()->andReturn(false, true);
    $scheduler->shouldReceive('requestNotificationPermission')->once()->withArgs(fn (string $requestId): bool => $requestId !== '');
    $scheduler->shouldReceive('schedule')->once();
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $editor = Native::visit('/alarms/new')
        ->tap('save-alarm')
        ->assertSet('awaitingPermission', true);

    $editor->emitNative(NotificationAuthorizationChanged::class, [
        'granted' => true,
        'requestId' => $editor->get('notificationPermissionRequestId'),
    ])->assertReplacedWith('/');
});

it('requires every answer to be correct before the alarm can be turned off', function () {
    $alarm = Alarm::factory()->create();
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00Z'));

    answerChallenge(Native::visit('/challenge'), false)
        ->assertSee('Necesitás 3 de 3.');

    $this->assertDatabaseHas('alarm_challenge_attempts', [
        'correct_answers' => 0,
        'question_count' => 3,
        'required_correct_answers' => 3,
        'passed' => false,
    ]);
});

it('replaces a failed challenge with unused questions and records every attempt', function () {
    $alarm = Alarm::factory()->create();
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00Z'));

    $challenge = answerChallenge(Native::visit('/challenge'), false)
        ->assertSee('Necesitás 3 de 3.');

    $firstQuestionIds = array_column($challenge->get('questions'), 'id');

    $challenge->tap('retry-challenge')
        ->assertSet('attemptNumber', 2);

    expect(array_intersect($firstQuestionIds, array_column($challenge->get('questions'), 'id')))->toBeEmpty();
    $this->assertDatabaseCount('alarm_challenge_attempts', 1);
});

it('stops a completed weekly alarm immediately', function () {
    $alarm = Alarm::factory()->create();
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00Z'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    $scheduler->shouldNotReceive('cancel');
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = answerChallenge(Native::visit('/challenge', ['alarmId' => $alarm->id]))
        ->assertSee('Alarma apagada')
        ->assertSet('alarmStopped', true)
        ->assertSee('Volver al inicio');

    expect($alarm->fresh())
        ->enabled->toBeTrue()
        ->scheduling_status->toBe('scheduled');
});

it('cancels and deactivates a completed one-time alarm immediately', function () {
    $alarm = Alarm::factory()->create(['weekdays' => []]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00Z'));
    $scheduler->shouldReceive('cancel')->once()->with($alarm->id);
    $scheduler->shouldNotReceive('completeRinging');
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = answerChallenge(Native::visit('/challenge', ['alarmId' => $alarm->id]))
        ->assertSet('alarmStopped', true);

    expect($alarm->fresh())
        ->enabled->toBeFalse()
        ->scheduling_status->toBe('not_scheduled');
});

it('completes a real weekly execution without creating history for its next schedule', function () {
    $alarm = Alarm::factory()->create();
    $execution = AlarmExecution::factory()->for($alarm)->create([
        'id' => '018f0b8d-1d3e-7f14-8caa-111111111111',
        'status' => 'scheduled',
        'finished_at' => null,
    ]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, $execution->id, '2026-09-03T07:00:00Z'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    answerChallenge(Native::visit('/challenge', [
        'alarmId' => $alarm->id,
        'executionId' => $execution->id,
        'scheduledFor' => '2026-09-03T07:00:00Z',
    ]));

    expect($execution->fresh()->status)->toBe('completed')
        ->and($alarm->fresh()->enabled)->toBeTrue();
    $this->assertDatabaseCount('alarm_executions', 1);
});

it('snoozes a real execution for the alarm-selected duration without changing its weekly schedule', function (int $minutes) {
    $alarm = Alarm::factory()->create(['snooze_enabled' => true, 'snooze_minutes' => $minutes]);
    $execution = AlarmExecution::factory()->for($alarm)->create([
        'id' => '018f0b8d-1d3e-7f14-8caa-111111111111',
        'status' => 'scheduled',
        'finished_at' => null,
    ]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, $execution->id, '2026-09-03T07:00:00Z'));
    $scheduler->shouldReceive('snooze')->once()->with($alarm->id, $minutes);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    Native::visit('/challenge', [
        'alarmId' => $alarm->id,
        'executionId' => $execution->id,
        'scheduledFor' => '2026-09-03T07:00:00Z',
    ])
        ->tap('snooze-alarm')
        ->assertReplacedWith('/');

    expect($execution->fresh()->status)->toBe('snoozed')
        ->and($execution->fresh()->snooze_count)->toBe(1)
        ->and($alarm->fresh()->scheduling_status)->toBe('scheduled');
})->with([
    'five minutes' => 5,
    'ten minutes' => 10,
    'fifteen minutes' => 15,
]);

it('returns home without stopping an alarm missing from the local database', function () {
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence('missing-alarm', 'execution-1', '2026-09-03T07:00:00Z'));
    $scheduler->shouldNotReceive('completeRinging');

    Native::visit('/challenge', ['alarmId' => 'missing-alarm'])
        ->assertReplacedWith('/');
});

it('shows success after three correct answers, then returns home after the alarm stops', function () {
    $alarm = Alarm::factory()->create();
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00Z'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    answerChallenge(Native::visit('/challenge', ['alarmId' => $alarm->id]))
        ->assertSee('Alarma apagada')
        ->assertNoNavigation()
        ->assertSee('Volver al inicio')
        ->tap('return-home')
        ->assertReplacedWith('/');

    $this->assertDatabaseHas('alarm_challenge_attempts', [
        'alarm_id' => $alarm->id,
        'correct_answers' => 3,
        'passed' => true,
    ]);
});

function answerChallenge($challenge, bool $correct = true)
{
    foreach ($challenge->get('questions') as $question) {
        $answerIndex = array_find_key($question['options'], fn (string $option): bool => $correct
            ? $option === $question['answer']
            : $option !== $question['answer']);
        $challenge->tap("answer-{$answerIndex}")->tap('continue-challenge');
    }

    return $challenge;
}
