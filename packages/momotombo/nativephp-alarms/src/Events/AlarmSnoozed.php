<?php

namespace Momotombo\NativePHPAlarms\Events;

final readonly class AlarmSnoozed
{
    public function __construct(public string $alarmId, public int $minutes) {}
}
