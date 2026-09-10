<?php

namespace App\AlarmScheduling;

use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Models\Alarm;
use App\Models\AlarmExecution;
use Momotombo\NativePHPAlarms\Exceptions\NativeAlarmSchedulingFailed;

final class ActiveAlarmOccurrenceResolver
{
    public function __construct(
        private AlarmOccurrenceReconciler $reconciler,
        private NativeAlarmScheduler $scheduler,
    ) {}

    public function resolve(): ?ActiveAlarmOccurrence
    {
        $this->reconciler->reconcile();

        try {
            $occurrence = $this->scheduler->activeRingingOccurrence();
        } catch (NativeAlarmSchedulingFailed $exception) {
            report($exception);

            return null;
        }

        if ($occurrence === null || ! Alarm::query()->whereKey($occurrence->alarmId)->exists()) {
            return null;
        }

        $execution = AlarmExecution::query()->find($occurrence->executionId);

        if ($execution !== null && ($execution->alarm_id !== $occurrence->alarmId
            || ! in_array($execution->status, ['scheduled', 'ringing', 'snoozed'], true))) {
            return null;
        }

        return $occurrence;
    }
}
