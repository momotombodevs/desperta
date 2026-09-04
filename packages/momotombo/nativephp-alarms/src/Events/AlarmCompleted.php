<?php

namespace Momotombo\NativePHPAlarms\Events;

final readonly class AlarmCompleted
{
    public function __construct(public string $alarmId) {}
}
