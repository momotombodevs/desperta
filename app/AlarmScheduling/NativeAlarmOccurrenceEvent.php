<?php

namespace App\AlarmScheduling;

final readonly class NativeAlarmOccurrenceEvent
{
    public function __construct(
        public string $alarmId,
        public string $executionId,
        public string $scheduledFor,
        public string $status,
        public string $occurredAt,
        public int $snoozeCount = 0,
    ) {}
}
