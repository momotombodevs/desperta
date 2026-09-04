<?php

namespace Momotombo\NativePHPAlarms\Events;

final readonly class AlarmTriggered
{
    /** @param array<string, mixed> $metadata */
    public function __construct(public string $alarmId, public array $metadata = []) {}
}
