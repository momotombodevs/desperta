<?php

namespace App\Application\AlarmScheduling;

final readonly class AlarmSchedule
{
    /** @param list<int> $weekdays */
    public function __construct(
        public string $id,
        public string $time,
        public string $label,
        public array $weekdays,
        public bool $vibration,
        public bool $snoozeEnabled,
        public string $difficulty,
        public int $snoozeMinutes = 5,
    ) {}
}
