<?php

namespace App\AlarmScheduling;

final readonly class ActiveAlarmOccurrence
{
    public function __construct(
        public string $alarmId,
        public string $executionId,
        public string $scheduledFor,
    ) {}
}
