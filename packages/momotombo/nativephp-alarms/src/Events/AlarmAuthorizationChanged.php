<?php

namespace Momotombo\NativePHPAlarms\Events;

use Momotombo\NativePHPAlarms\Enums\AuthorizationStatus;

final readonly class AlarmAuthorizationChanged
{
    public function __construct(public AuthorizationStatus $status) {}
}
