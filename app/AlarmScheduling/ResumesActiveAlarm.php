<?php

namespace App\AlarmScheduling;

use Momotombo\NativePHPAlarms\Events\AppResumed;
use Native\Mobile\Attributes\On;

trait ResumesActiveAlarm
{
    public string $activeAlarmId = '';

    #[On(AppResumed::class)]
    public function handleAppResumed(): void
    {
        $this->resumeActiveAlarm();
    }

    protected function resumeActiveAlarm(): bool
    {
        $occurrence = $this->refreshActiveOccurrence();

        if ($occurrence === null || $this->isShowingActiveAlarm($occurrence)) {
            return false;
        }

        $this->replace('/challenge', [
            'alarmId' => $occurrence->alarmId,
            'executionId' => $occurrence->executionId,
            'scheduledFor' => $occurrence->scheduledFor,
        ]);

        return true;
    }

    protected function refreshActiveOccurrence(): ?ActiveAlarmOccurrence
    {
        $occurrence = app(ActiveAlarmOccurrenceResolver::class)->resolve();
        $this->activeAlarmId = $occurrence?->alarmId ?? '';

        return $occurrence;
    }

    protected function isShowingActiveAlarm(ActiveAlarmOccurrence $occurrence): bool
    {
        return false;
    }
}
