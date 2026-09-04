<?php

use App\Application\AlarmScheduling\AlarmExecutionLifecycle;
use App\Models\Alarm;
use App\Models\AlarmExecution;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('records the scheduled native occurrence before it reaches the device', function () {
    $this->travelTo('2026-09-03 06:00:00');
    $alarm = Alarm::factory()->create(['time' => '07:15', 'weekdays' => [4]]);

    $lifecycle = app(AlarmExecutionLifecycle::class);
    $schedule = $lifecycle->scheduleFor($alarm);

    expect($schedule->scheduledFor)->toBe('2026-09-03T13:15:00+00:00')
        ->and(AlarmExecution::query()->whereKey($schedule->executionId)->firstOrFail())
        ->alarm_id->toBe($alarm->id)
        ->and(AlarmExecution::query()->whereKey($schedule->executionId)->value('status'))->toBe('scheduled');
});

it('marks an older open execution as missed when the next one begins', function () {
    $alarm = Alarm::factory()->create();
    $missed = AlarmExecution::factory()->for($alarm)->create(['status' => 'snoozed', 'finished_at' => null]);
    $executionId = '018f0b8d-1d3e-7f14-8caa-111111111111';

    $execution = app(AlarmExecutionLifecycle::class)->begin($alarm->id, $executionId, '2026-09-03T07:00:00+00:00');

    expect($execution?->status)->toBe('ringing')
        ->and($missed->fresh()->status)->toBe('missed');
});

it('cancels open executions while preserving completed history after its alarm is deleted', function () {
    $alarm = Alarm::factory()->create();
    $completed = AlarmExecution::factory()->for($alarm)->create(['status' => 'completed']);
    $open = AlarmExecution::factory()->for($alarm)->create(['status' => 'scheduled', 'finished_at' => null]);

    app(AlarmExecutionLifecycle::class)->cancelOpen($alarm);
    $alarm->delete();

    expect($completed->fresh()->status)->toBe('completed')
        ->and($completed->fresh()->alarm_id)->toBeNull()
        ->and($open->fresh()->status)->toBe('cancelled');
});
