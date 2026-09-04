<?php

namespace Momotombo\NativePHPAlarms\Events;

final readonly class AlarmError
{
    /** @param array<string, mixed> $context */
    public function __construct(public string $code, public string $message, public array $context = []) {}
}
