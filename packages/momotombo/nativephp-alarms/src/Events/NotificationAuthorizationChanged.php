<?php

namespace Momotombo\NativePHPAlarms\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class NotificationAuthorizationChanged
{
    use Dispatchable;

    public function __construct(
        public bool $granted,
        public string $requestId,
    ) {}
}
