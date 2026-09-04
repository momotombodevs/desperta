<?php

namespace App\AlarmScheduling;

use App\Application\AlarmScheduling\AlarmExecutionLifecycle;
use App\Application\AlarmScheduling\NativeAlarmGateway;
use Illuminate\Support\Facades\DB;
use Momotombo\NativePHPAlarms\Exceptions\AlarmException;

final class AlarmOccurrenceReconciler
{
    public function __construct(
        private NativeAlarmGateway $alarms,
        private AlarmExecutionLifecycle $lifecycle,
    ) {}

    public function reconcile(): void
    {
        $acknowledged = [];

        try {
            $events = $this->alarms->occurrenceEvents();
        } catch (AlarmException $exception) {
            report($exception);

            return;
        }

        foreach ($events as $event) {
            if (DB::transaction(fn (): bool => $this->lifecycle->reconcile($event))) {
                $acknowledged[] = $event->executionId;
            }
        }

        try {
            $this->alarms->acknowledgeOccurrenceEvents($acknowledged);
        } catch (AlarmException $exception) {
            report($exception);
        }
    }
}
