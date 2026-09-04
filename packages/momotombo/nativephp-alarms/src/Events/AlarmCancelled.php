<?php

namespace Momotombo\NativePHPAlarms\Events;

final readonly class AlarmCancelled
{
    public function __construct(public string $alarmId) {}
}
