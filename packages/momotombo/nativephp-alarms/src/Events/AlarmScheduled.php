<?php

namespace Momotombo\NativePHPAlarms\Events;

use Momotombo\NativePHPAlarms\DTO\AlarmConfiguration;

final readonly class AlarmScheduled
{
    public function __construct(public AlarmConfiguration $alarm) {}
}
