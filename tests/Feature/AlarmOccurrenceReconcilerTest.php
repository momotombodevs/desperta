<?php

use App\AlarmScheduling\AlarmOccurrenceReconciler;
use App\AlarmScheduling\NativeAlarmOccurrenceEvent;
use App\Application\AlarmScheduling\NativeAlarmGateway;
use App\Models\Alarm;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

it('reconciles a native completion once and acknowledges its execution', function () {
    $alarm = Alarm::factory()->create();
    $event = new NativeAlarmOccurrenceEvent(
        alarmId: $alarm->id,
        executionId: '018f0b8d-1d3e-7f14-8caa-111111111111',
        scheduledFor: '2026-09-03T07:00:00+00:00',
        status: 'completed',
        occurredAt: '2026-09-03T07:08:00+00:00',
    );
    $gateway = mock(NativeAlarmGateway::class);
    $gateway->shouldReceive('occurrenceEvents')->once()->andReturn([$event]);
    $gateway->shouldReceive('acknowledgeOccurrenceEvents')->once()->with([$event->executionId]);
    app()->instance(NativeAlarmGateway::class, $gateway);

    app(AlarmOccurrenceReconciler::class)->reconcile();

    $this->assertDatabaseHas('alarm_executions', [
        'id' => $event->executionId,
        'alarm_id' => $alarm->id,
        'status' => 'completed',
        'finished_at' => '2026-09-03 07:08:00',
    ]);
});
